<?php

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Exceptions\InvalidRoleContext;
use App\Domain\Identity\Exceptions\RoleContextMissing;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\CheckActiveRoleAssignment;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

function roleContextAssignment(
    User $user,
    Organisation $organisation,
    SystemRole $systemRole,
    ?Branch $branch = null,
): OrganisationUserRole {
    $role = Role::findByName($systemRole->value);

    return OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'role_id' => $role->getKey(),
            'branch_id' => $branch?->getKey(),
        ])
        ->create();
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('organisation wide and matching branch assignments establish role context', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $organisationWide = roleContextAssignment($user, $organisation, SystemRole::Treasurer);
    $branchSpecific = roleContextAssignment($user, $organisation, SystemRole::Collector, $branch);
    $context = app(TenantContext::class);

    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($organisationWide);

    expect($context->roleAssignment()->is($organisationWide))->toBeTrue();

    $context->setRoleAssignment($branchSpecific);

    expect($context->roleAssignment()->is($branchSpecific))->toBeTrue();
});

test('branch assignment without its matching branch is rejected', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $assignment = roleContextAssignment($user, $organisation, SystemRole::Collector, $branch);
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);

    $context->setRoleAssignment($assignment);
})->throws(InvalidRoleContext::class);

test('assignment from another organisation is rejected', function () {
    $user = User::factory()->create();
    $activeOrganisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $assignment = roleContextAssignment($user, $otherOrganisation, SystemRole::Member);
    $context = app(TenantContext::class);
    $context->setOrganisation($activeOrganisation);

    $context->setRoleAssignment($assignment);
})->throws(InvalidRoleContext::class);

test('an assignment belonging to another user fails active assignment verification', function () {
    $organisation = Organisation::factory()->create();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $assignment = roleContextAssignment($owner, $organisation, SystemRole::Member);

    expect(app(CheckActiveRoleAssignment::class)->passes(
        $assignment,
        $otherUser,
        $organisation,
        null,
    ))->toBeFalse();
});

test('missing and cleared role context fails safely', function () {
    $context = app(TenantContext::class);
    $context->setOrganisation(Organisation::factory()->create());

    expect(fn () => $context->role())->toThrow(RoleContextMissing::class);

    $assignment = OrganisationUserRole::factory()->create();
    $context->setOrganisation($assignment->organisation);
    $context->setRoleAssignment($assignment);
    $context->clear();

    expect(fn () => $context->role())->toThrow(RoleContextMissing::class);
});

test('permissions come only from the selected active role', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $administrator = roleContextAssignment(
        $user,
        $organisation,
        SystemRole::OrganisationAdmin,
    );
    $member = roleContextAssignment($user, $organisation, SystemRole::Member);
    $context = app(TenantContext::class);
    $authorization = app(RoleAuthorization::class);

    $context->setOrganisation($organisation);
    $context->setRoleAssignment($administrator);
    expect($authorization->can(FoundationPermission::RolesAssign->value))->toBeTrue();

    $context->setRoleAssignment($member);
    expect($authorization->can(FoundationPermission::RolesAssign->value))->toBeFalse()
        ->and($authorization->isPlatformAdministrator())->toBeFalse();

});

test('platform administration is explicit and not an organisation permission bypass', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $platformAssignment = roleContextAssignment(
        $user,
        $organisation,
        SystemRole::PlatformAdmin,
    );
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setRoleAssignment($platformAssignment);
    $authorization = app(RoleAuthorization::class);

    expect($authorization->isPlatformAdministrator())->toBeTrue()
        ->and($authorization->can(FoundationPermission::RolesAssign->value))->toBeFalse();
});
