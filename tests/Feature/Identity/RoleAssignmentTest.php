<?php

use App\Domain\Identity\Actions\AssignRoleToUser;
use App\Domain\Identity\Actions\RevokeUserRole;
use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Exceptions\InvalidRoleAssignment;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

function establishAssigningContext(
    User $actor,
    Organisation $organisation,
    ?Branch $branch = null,
): OrganisationUserRole {
    OrganisationUser::factory()->for($actor)->for($organisation)->create();
    $role = Role::findByName(SystemRole::OrganisationAdmin->value);
    $assignment = OrganisationUserRole::factory()
        ->for($actor)
        ->for($organisation)
        ->state([
            'role_id' => $role->getKey(),
            'branch_id' => null,
        ])
        ->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment);

    return $assignment;
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('an active organisation user may receive multiple roles', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    establishAssigningContext($actor, $organisation);
    $assign = app(AssignRoleToUser::class);

    $treasurer = $assign->handle(
        $actor,
        $organisation,
        $target,
        Role::findByName(SystemRole::Treasurer->value),
    );
    $auditor = $assign->handle(
        $actor,
        $organisation,
        $target,
        Role::findByName(SystemRole::Auditor->value),
    );

    expect($treasurer->isCurrentlyValid())->toBeTrue()
        ->and($auditor->isCurrentlyValid())->toBeTrue()
        ->and($target->organisationRoleAssignments()->count())->toBe(2);
});

test('a user without active organisation access cannot receive a role', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishAssigningContext($actor, $organisation);

    app(AssignRoleToUser::class)->handle(
        $actor,
        $organisation,
        $target,
        Role::findByName(SystemRole::Member->value),
    );
})->throws(InvalidRoleAssignment::class);

test('same role may be assigned in different valid branches and organisations', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $firstOrganisation = Organisation::factory()->create();
    $secondOrganisation = Organisation::factory()->create();
    $firstBranches = Branch::factory()->count(2)->for($firstOrganisation)->create();
    $role = Role::findByName(SystemRole::Collector->value);
    $assign = app(AssignRoleToUser::class);

    OrganisationUser::factory()->for($target)->for($firstOrganisation)->create();
    establishAssigningContext($actor, $firstOrganisation);
    foreach ($firstBranches as $branch) {
        $assign->handle($actor, $firstOrganisation, $target, $role, $branch);
    }

    OrganisationUser::factory()->for($target)->for($secondOrganisation)->create();
    establishAssigningContext($actor, $secondOrganisation);
    $assign->handle($actor, $secondOrganisation, $target, $role);

    expect($target->organisationRoleAssignments()->count())->toBe(3);
});

test('a branch must belong to the assignment organisation', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherBranch = Branch::factory()->create();
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    establishAssigningContext($actor, $organisation);

    app(AssignRoleToUser::class)->handle(
        $actor,
        $organisation,
        $target,
        Role::findByName(SystemRole::Collector->value),
        $otherBranch,
    );
})->throws(InvalidRoleAssignment::class);

test('duplicate active assignments are rejected but revoked history is retained', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $role = Role::findByName(SystemRole::Auditor->value);
    OrganisationUser::factory()->for($target)->for($organisation)->create();
    establishAssigningContext($actor, $organisation);
    $assign = app(AssignRoleToUser::class);
    $assignment = $assign->handle($actor, $organisation, $target, $role);

    expect(fn () => $assign->handle($actor, $organisation, $target, $role))
        ->toThrow(InvalidRoleAssignment::class);

    $revoked = app(RevokeUserRole::class)->handle($assignment, $actor, 'Changed duties');
    $replacement = $assign->handle($actor, $organisation, $target, $role);

    expect($revoked->status)->toBe(RoleAssignmentStatus::Revoked)
        ->and($revoked->revoked_by_user_id)->toBe($actor->id)
        ->and($revoked->revoked_at)->not->toBeNull()
        ->and($revoked->revocation_reason)->toBe('Changed duties')
        ->and($replacement->id)->not->toBe($revoked->id)
        ->and(OrganisationUserRole::query()->count())->toBe(3);
});

test('inactive and out of date assignments are not currently valid', function (
    string $factoryState,
) {
    $assignment = OrganisationUserRole::factory()->{$factoryState}()->create();

    expect($assignment->isCurrentlyValid())->toBeFalse();
})->with([
    'suspended' => 'suspended',
    'revoked' => 'revoked',
    'expired' => 'expired',
    'future dated' => 'futureDated',
    'pending' => 'pending',
]);
