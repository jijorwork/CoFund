<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

function apiContextAssignment(
    User $user,
    Organisation $organisation,
    SystemRole $systemRole,
    ?Branch $branch = null,
): OrganisationUserRole {
    return OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'role_id' => Role::findByName($systemRole->value)->getKey(),
            'branch_id' => $branch?->getKey(),
        ])
        ->create();
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('context listing returns only currently valid accessible contexts without internal ids', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $valid = apiContextAssignment($user, $organisation, SystemRole::Treasurer, $branch);
    OrganisationUserRole::factory()->for($user)->for($organisation)->revoked()->state([
        'role_id' => Role::findByName(SystemRole::Member->value)->getKey(),
    ])->create();
    OrganisationUserRole::factory()->for($user)->for($organisation)->expired()->state([
        'role_id' => Role::findByName(SystemRole::Collector->value)->getKey(),
    ])->create();
    OrganisationUserRole::factory()->for($user)->for($organisation)->futureDated()->state([
        'role_id' => Role::findByName(SystemRole::Approver->value)->getKey(),
    ])->create();
    $suspendedOrganisation = Organisation::factory()->suspended()->create();
    apiContextAssignment($user, $suspendedOrganisation, SystemRole::Member);
    $suspendedBranch = Branch::factory()->for($organisation)->suspended()->create();
    apiContextAssignment($user, $organisation, SystemRole::Auditor, $suspendedBranch);

    $response = $this->actingAs($user)->getJson(route('api.v1.auth.contexts.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.role_assignment_uuid', $valid->public_id)
        ->assertJsonPath('data.0.organisation_uuid', $organisation->public_id)
        ->assertJsonPath('data.0.branch_uuid', $branch->public_id)
        ->assertJsonPath('data.0.role_name', SystemRole::Treasurer->value)
        ->assertJsonMissing(['id' => $valid->id])
        ->assertJsonMissing(['organisation_id' => $organisation->id]);
});

test('an authenticated user can switch to a valid branch context stored in session', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = apiContextAssignment($user, $organisation, SystemRole::Collector, $branch);

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.context.store'), [
            'organisation_uuid' => $organisation->public_id,
            'branch_uuid' => $branch->public_id,
            'role_assignment_uuid' => $assignment->public_id,
        ])
        ->assertOk()
        ->assertJsonPath('data.role_assignment_uuid', $assignment->public_id)
        ->assertSessionHas('active_context.organisation_public_id', $organisation->public_id)
        ->assertSessionHas('active_context.branch_public_id', $branch->public_id)
        ->assertSessionHas('active_context.role_assignment_public_id', $assignment->public_id);
});

test('organisation wide assignment may select an active branch in its organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = apiContextAssignment($user, $organisation, SystemRole::Auditor);

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.context.store'), [
            'organisation_uuid' => $organisation->public_id,
            'branch_uuid' => $branch->public_id,
            'role_assignment_uuid' => $assignment->public_id,
        ])
        ->assertOk();
});

test('inaccessible and mismatched selections return the same generic forbidden response', function (
    string $variation,
) {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($otherOrganisation)->create();
    $ownAssignment = apiContextAssignment($user, $organisation, SystemRole::Member, $branch);
    $otherAssignment = apiContextAssignment($otherUser, $organisation, SystemRole::Member, $branch);

    $payload = match ($variation) {
        'another user' => [
            'organisation_uuid' => $organisation->public_id,
            'branch_uuid' => $branch->public_id,
            'role_assignment_uuid' => $otherAssignment->public_id,
        ],
        'another organisation' => [
            'organisation_uuid' => $otherOrganisation->public_id,
            'branch_uuid' => null,
            'role_assignment_uuid' => $ownAssignment->public_id,
        ],
        'another branch' => [
            'organisation_uuid' => $organisation->public_id,
            'branch_uuid' => $otherBranch->public_id,
            'role_assignment_uuid' => $ownAssignment->public_id,
        ],
    };

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.context.store'), $payload)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid')
        ->assertJsonPath('error.message', 'The selected role context is unavailable.');
})->with(['another user', 'another organisation', 'another branch']);

test('persisted context is rejected after assignment revocation or organisation suspension', function (
    string $staleReason,
) {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = apiContextAssignment($user, $organisation, SystemRole::Auditor);

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.context.store'), [
            'organisation_uuid' => $organisation->public_id,
            'role_assignment_uuid' => $assignment->public_id,
        ])
        ->assertOk();

    if ($staleReason === 'revoked') {
        $assignment->update([
            'status' => 'revoked',
            'active_scope_key' => null,
            'revoked_at' => now(),
        ]);
    } else {
        $organisation->update(['status' => 'suspended']);
    }

    $this->getJson(route('api.v1.roles.index'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');
})->with(['revoked', 'suspended organisation']);
