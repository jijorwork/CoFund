<?php

use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\ContextualAccessToken;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('administrator issues a read token fixed to the selected organisation branch and role', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $otherOrganisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::OrganisationAdmin, $branch);

    $visible = Member::factory()->for($organisation)->create(['branch_id' => $branch->id]);
    $otherBranchMember = Member::factory()->for($organisation)->create(['branch_id' => $otherBranch->id]);
    $otherOrganisationMember = Member::factory()->for($otherOrganisation)->create();
    $visibleGroup = Group::factory()->for($organisation)->create(['branch_id' => $branch->id]);
    $otherBranchGroup = Group::factory()->for($organisation)->create(['branch_id' => $otherBranch->id]);

    $issued = $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Reporting client',
        'expires_in_days' => 30,
    ])->assertCreated()
        ->assertJsonPath('data.organisation_uuid', $organisation->public_id)
        ->assertJsonPath('data.branch_uuid', $branch->public_id);

    $plainTextToken = $issued->json('data.plain_text_token');
    Auth::guard('web')->logout();

    $this->withToken($plainTextToken)->withHeaders([
        'X-Organisation-ID' => $otherOrganisation->public_id,
        'X-Branch-ID' => $otherBranch->public_id,
    ])->getJson(route('api.v1.external.context'))
        ->assertOk()
        ->assertJsonPath('data.organisation_uuid', $organisation->public_id)
        ->assertJsonPath('data.branch_uuid', $branch->public_id);

    $this->getJson(route('api.v1.external.members.index'))
        ->assertOk()
        ->assertJsonFragment(['uuid' => $visible->public_id])
        ->assertJsonMissing(['uuid' => $otherBranchMember->public_id])
        ->assertJsonMissing(['uuid' => $otherOrganisationMember->public_id]);

    $this->getJson(route('api.v1.external.members.show', $otherBranchMember->public_id))
        ->assertNotFound();
    $this->getJson(route('api.v1.external.members.show', $visible->public_id))
        ->assertOk();
    $this->getJson(route('api.v1.external.dashboard'))
        ->assertOk();
    $this->getJson(route('api.v1.external.groups.index'))
        ->assertOk()
        ->assertJsonFragment(['uuid' => $visibleGroup->public_id])
        ->assertJsonMissing(['uuid' => $otherBranchGroup->public_id]);
    $this->getJson(route('api.v1.external.groups.show', $otherBranchGroup->public_id))
        ->assertNotFound();
    $this->getJson(route('api.v1.external.audit-events.index'))
        ->assertForbidden();
    $this->getJson(route('api.v1.external.reports.trial-balance'))
        ->assertOk();
    foreach ([
        'contribution-plans.index',
        'contribution-enrolments.index',
        'contribution-payments.index',
        'wallets.index',
        'receipts.index',
        'reports.general-ledger',
        'reports.statement-of-financial-position',
        'reports.income-and-expenditure',
        'reports.cash-flow',
    ] as $readRoute) {
        $this->getJson(route('api.v1.external.'.$readRoute))->assertOk();
    }
});

test('token becomes invalid when its role assignment is revoked', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::OrganisationAdmin);

    $issued = $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Reporting client',
        'expires_in_days' => 30,
    ])->assertCreated();

    $assignment = OrganisationUserRole::query()->where('public_id', $issued->json('data.role_assignment_uuid'))->firstOrFail();
    $assignment->update(['status' => 'revoked', 'active_scope_key' => null, 'revoked_at' => now()]);
    Auth::guard('web')->logout();

    $this->withToken($issued->json('data.plain_text_token'))
        ->getJson(route('api.v1.external.context'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');

});

test('session or missing credentials cannot use token routes', function () {
    $this->getJson(route('api.v1.external.context'))->assertUnauthorized();

    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::OrganisationAdmin);

    $this->getJson(route('api.v1.external.context'))->assertForbidden();
});

test('token creation validates expiry and exposes the secret only once', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::OrganisationAdmin);

    $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Reporting client',
        'expires_in_days' => 91,
    ])->assertUnprocessable();

    $issued = $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Reporting client',
        'expires_in_days' => 30,
    ])->assertCreated();

    $uuid = $issued->json('data.uuid');
    expect(AuditEvent::query()->where('event_key', 'api_token.issued')->where('subject_public_id', $uuid)->exists())
        ->toBeTrue();
    $this->getJson(route('api.v1.auth.tokens.index'))
        ->assertOk()
        ->assertJsonPath('data.tokens.0.uuid', $uuid)
        ->assertJsonMissing(['plain_text_token' => $issued->json('data.plain_text_token')]);

    $this->deleteJson(route('api.v1.auth.tokens.destroy', $uuid))->assertOk();
    expect(AuditEvent::query()->where('event_key', 'api_token.revoked')->where('subject_public_id', $uuid)->exists())
        ->toBeTrue();
    Auth::guard('web')->logout();
    $this->withToken($issued->json('data.plain_text_token'))
        ->getJson(route('api.v1.external.context'))->assertUnauthorized();
});

test('users without role management permission cannot issue tokens', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::Member);

    $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Reporting client',
        'expires_in_days' => 30,
    ])->assertForbidden();

    expect(ContextualAccessToken::query()->count())->toBe(0);
});

test('expired tokens and tokens with mismatched stored assignments are rejected', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::OrganisationAdmin);

    $issued = $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Reporting client',
        'expires_in_days' => 30,
    ])->assertCreated();

    $token = ContextualAccessToken::query()->where('public_id', $issued->json('data.uuid'))->firstOrFail();
    $token->update(['organisation_id' => $otherOrganisation->id]);
    Auth::guard('web')->logout();

    $this->withToken($issued->json('data.plain_text_token'))
        ->getJson(route('api.v1.external.context'))
        ->assertForbidden();

    $token->update(['organisation_id' => $organisation->id, 'expires_at' => now()->subMinute()]);
    Auth::guard('sanctum')->forgetUser();
    $this->getJson(route('api.v1.external.context'))->assertUnauthorized();
});

test('a token is invalid when the assigned role changes after issuance', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::OrganisationAdmin);

    $issued = $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Reporting client',
        'expires_in_days' => 30,
    ])->assertCreated();
    $assignment = OrganisationUserRole::query()->where('public_id', $issued->json('data.role_assignment_uuid'))->firstOrFail();
    $assignment->update(['role_id' => Role::findByName(SystemRole::Treasurer->value)->getKey()]);
    Auth::guard('web')->logout();

    $this->withToken($issued->json('data.plain_text_token'))
        ->getJson(route('api.v1.external.context'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');

    $assignment->update(['role_id' => Role::findByName(SystemRole::OrganisationAdmin->value, 'web')->getKey()]);
    ContextualAccessToken::query()->where('public_id', $issued->json('data.uuid'))->update(['role_id' => null]);
    Auth::guard('sanctum')->forgetUser();
    $this->getJson(route('api.v1.external.context'))
        ->assertForbidden();
});

test('an auditor can issue a token for its own role and read only its branch audit events', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    selectMembershipApiContext($this, $user, $organisation, SystemRole::Auditor, $branch);

    $issued = $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Audit export',
        'expires_in_days' => 7,
    ])->assertCreated();
    AuditEvent::query()->create([
        'organisation_id' => $organisation->id,
        'branch_id' => $otherBranch->id,
        'actor_user_id' => $user->id,
        'acting_role_id' => Role::findByName(SystemRole::Auditor->value)->getKey(),
        'role_assignment_id' => OrganisationUserRole::query()->where('public_id', $issued->json('data.role_assignment_uuid'))->value('id'),
        'event_key' => 'other_branch.event',
        'subject_type' => 'member',
        'subject_public_id' => fake()->uuid(),
        'details' => [],
    ]);
    Auth::guard('web')->logout();

    $this->withToken($issued->json('data.plain_text_token'))
        ->getJson(route('api.v1.external.audit-events.index'))
        ->assertOk()
        ->assertJsonFragment(['event_key' => 'api_token.issued'])
        ->assertJsonMissing(['event_key' => 'other_branch.event']);
});
