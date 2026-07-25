<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Tests\TestCase;

function selectApiRoleContext(
    TestCase $test,
    User $user,
    Organisation $organisation,
    SystemRole $systemRole,
    ?Branch $assignmentBranch = null,
): OrganisationUserRole {
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'role_id' => Role::findByName($systemRole->value)->getKey(),
            'branch_id' => $assignmentBranch?->getKey(),
        ])
        ->create();

    $test->actingAs($user)
        ->postJson(route('api.v1.auth.context.store'), [
            'organisation_uuid' => $organisation->public_id,
            'branch_uuid' => $assignmentBranch?->public_id,
            'role_assignment_uuid' => $assignment->public_id,
        ])
        ->assertOk();

    return $assignment;
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('role assignment API requires authentication and selected context', function () {
    $this->getJson(route('api.v1.role-assignments.index'))->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.v1.role-assignments.index'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');
});

test('missing role context is rejected after tenant resolution', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    OrganisationUser::factory()->for($user)->for($organisation)->create();

    $this->actingAs($user)
        ->withSession([
            'active_context' => [
                'organisation_public_id' => $organisation->public_id,
                'branch_public_id' => null,
            ],
        ])
        ->getJson(route('api.v1.role-assignments.index'))
        ->assertForbidden()
        ->assertJsonPath('error.code', 'context_invalid');
});

test('roles view permission limits paginated assignments to the active organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    selectApiRoleContext($this, $user, $organisation, SystemRole::Auditor);
    $visible = OrganisationUserRole::factory()->for($organisation)->create();
    $hidden = OrganisationUserRole::factory()->for($otherOrganisation)->create();

    $this->getJson(route('api.v1.role-assignments.index'))
        ->assertOk()
        ->assertJsonFragment(['uuid' => $visible->public_id])
        ->assertJsonMissing(['uuid' => $hidden->public_id])
        ->assertJsonStructure([
            'data',
            'links',
            'meta' => ['current_page', 'request_id'],
        ]);
});

test('collector cannot list or assign roles while auditor may only view', function (
    SystemRole $systemRole,
    string $endpoint,
) {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectApiRoleContext($this, $user, $organisation, $systemRole);

    $response = $endpoint === 'list'
        ? $this->getJson(route('api.v1.role-assignments.index'))
        : $this->postJson(route('api.v1.role-assignments.store'), []);

    $response->assertForbidden()->assertJsonPath('error.code', 'forbidden');
})->with([
    'collector cannot list' => [SystemRole::Collector, 'list'],
    'auditor cannot assign' => [SystemRole::Auditor, 'assign'],
]);

test('organisation administrator can assign view and revoke a role by uuid', function () {
    $administrator = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    selectApiRoleContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);

    $createResponse = $this->postJson(route('api.v1.role-assignments.store'), [
        'user_uuid' => $target->public_id,
        'role_name' => SystemRole::Treasurer->value,
    ])->assertCreated()
        ->assertJsonPath('data.user.uuid', $target->public_id)
        ->assertJsonPath('data.role.name', SystemRole::Treasurer->value)
        ->assertJsonMissing(['user_id' => $target->id]);

    $assignmentUuid = $createResponse->json('data.uuid');

    $this->getJson(route('api.v1.role-assignments.show', $assignmentUuid))
        ->assertOk()
        ->assertJsonPath('data.uuid', $assignmentUuid);

    $this->postJson(route('api.v1.role-assignments.revoke', $assignmentUuid), [
        'reason' => 'No longer required',
    ])->assertOk()
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.revocation_reason', 'No longer required');
});

test('branch manager cannot manage an unrelated branch', function () {
    $manager = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $managerBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    selectApiRoleContext(
        $this,
        $manager,
        $organisation,
        SystemRole::BranchManager,
        $managerBranch,
    );

    $this->postJson(route('api.v1.role-assignments.store'), [
        'user_uuid' => $target->public_id,
        'role_name' => SystemRole::Collector->value,
        'branch_uuid' => $otherBranch->public_id,
    ])->assertForbidden();
});

test('available roles exclude platform administrator and expose no internals', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectApiRoleContext($this, $user, $organisation, SystemRole::Auditor);

    $this->getJson(route('api.v1.roles.index'))
        ->assertOk()
        ->assertJsonFragment(['name' => SystemRole::OrganisationAdmin->value])
        ->assertJsonMissing(['name' => SystemRole::PlatformAdmin->value])
        ->assertJsonMissing(['guard_name' => 'web']);
});

test('invalid assignment filters use the documented validation envelope', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectApiRoleContext($this, $user, $organisation, SystemRole::Auditor);

    $this->getJson(route('api.v1.role-assignments.index', [
        'filter' => ['status' => 'unknown'],
    ]))->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['details' => ['filter.status']]]);
});
