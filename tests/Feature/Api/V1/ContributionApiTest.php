<?php

use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Tests\TestCase;

function selectContributionApiContext(
    TestCase $test,
    User $user,
    Organisation $organisation,
    SystemRole $role,
    ?Branch $branch = null,
): void {
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName($role->value)->getKey(),
        ])->create();

    $test->actingAs($user)->postJson(route('api.v1.auth.context.store'), [
        'organisation_uuid' => $organisation->public_id,
        'branch_uuid' => $branch?->public_id,
        'role_assignment_uuid' => $assignment->public_id,
    ])->assertSuccessful();
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('administrator can create activate and enrol a member without exposing internal ids', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->create(['currency_code' => 'GHS']);
    selectContributionApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);
    $member = Member::factory()->active()->for($organisation)->create();

    $planResponse = $this->postJson(route('api.v1.contribution-plans.store'), [
        'name' => 'Monthly Development Fund',
        'code' => 'DEV-MONTHLY',
        'plan_type' => 'regular_savings',
        'frequency' => 'monthly',
        'amount_type' => 'fixed',
        'fixed_amount_minor' => 10000,
        'start_date' => now()->toDateString(),
        'collection_day_of_month' => min(now()->day, 28),
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonMissingPath('data.organisation_id');

    $planUuid = $planResponse->json('data.uuid');
    $this->postJson(route('api.v1.contribution-plans.activate', $planUuid))
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active');

    $enrolment = $this->postJson(route('api.v1.contribution-enrolments.store'), [
        'member_uuid' => $member->public_id,
        'plan_uuid' => $planUuid,
        'start_date' => now()->toDateString(),
    ])->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.position.position_status', 'current');

    $this->getJson(route('api.v1.contribution-enrolments.schedule.index', $enrolment->json('data.uuid')))
        ->assertSuccessful()
        ->assertJsonStructure(['data', 'links', 'meta' => ['request_id']])
        ->assertJsonMissingPath('data.0.organisation_id');
});

test('tenant and branch scope hide inaccessible contribution records', function () {
    $manager = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    selectContributionApiContext($this, $manager, $organisation, SystemRole::BranchManager, $branch);
    $visible = ContributionPlan::factory()->for($organisation)->create(['branch_id' => $branch->id]);
    $organisationWide = ContributionPlan::factory()->for($organisation)->create(['branch_id' => null]);
    $hidden = ContributionPlan::factory()->for($organisation)->create(['branch_id' => $otherBranch->id]);
    $foreign = ContributionPlan::factory()->create();

    $this->getJson(route('api.v1.contribution-plans.index'))
        ->assertSuccessful()
        ->assertJsonFragment(['uuid' => $visible->public_id])
        ->assertJsonFragment(['uuid' => $organisationWide->public_id])
        ->assertJsonMissing(['uuid' => $hidden->public_id])
        ->assertJsonMissing(['uuid' => $foreign->public_id]);

    $this->getJson(route('api.v1.contribution-plans.show', $hidden->public_id))->assertNotFound();
});

test('member role is denied and invalid filters use the standard error envelope', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectContributionApiContext($this, $user, $organisation, SystemRole::Member);
    $this->getJson(route('api.v1.contribution-plans.index'))->assertForbidden();

    $auditor = User::factory()->create();
    selectContributionApiContext($this, $auditor, $organisation, SystemRole::Auditor);
    $this->getJson(route('api.v1.contribution-plans.index', ['sort' => 'fixed_amount_minor']))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');
});
