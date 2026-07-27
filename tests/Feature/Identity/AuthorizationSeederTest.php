<?php

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\Role;
use Database\Seeders\SystemAuthorizationSeeder;
use Spatie\Permission\Models\Permission;

test('the authorization seeder creates the focused catalogue idempotently', function () {
    $this->seed(SystemAuthorizationSeeder::class);
    $this->seed(SystemAuthorizationSeeder::class);

    expect(Role::query()->count())->toBe(count(SystemRole::cases()))
        ->and(Permission::query()->count())->toBe(count(FoundationPermission::cases()));
});

test('the initial role permission matrix matches the approved foundation', function () {
    $this->seed(SystemAuthorizationSeeder::class);

    $organisationAdmin = Role::findByName(SystemRole::OrganisationAdmin->value);
    $auditor = Role::findByName(SystemRole::Auditor->value);
    $collector = Role::findByName(SystemRole::Collector->value);
    $member = Role::findByName(SystemRole::Member->value);
    $platformAdmin = Role::findByName(SystemRole::PlatformAdmin->value);

    expect($organisationAdmin->checkPermissionTo(FoundationPermission::RolesAssign->value))->toBeTrue()
        ->and($organisationAdmin->checkPermissionTo(FoundationPermission::RolesRevoke->value))->toBeTrue()
        ->and($auditor->checkPermissionTo(FoundationPermission::RolesView->value))->toBeTrue()
        ->and($auditor->checkPermissionTo(FoundationPermission::RolesAssign->value))->toBeFalse()
        ->and($collector->checkPermissionTo(FoundationPermission::MembersView->value))->toBeTrue()
        ->and($collector->checkPermissionTo(FoundationPermission::GroupsView->value))->toBeTrue()
        ->and($collector->checkPermissionTo(FoundationPermission::MembersViewDocuments->value))->toBeFalse()
        ->and($collector->checkPermissionTo(FoundationPermission::RolesAssign->value))->toBeFalse()
        ->and($member->permissions->pluck('name')->sort()->values()->all())->toBe([
            FoundationPermission::NotificationPreferencesUpdateOwn->value,
            FoundationPermission::NotificationPreferencesViewOwn->value,
            FoundationPermission::NotificationsArchiveOwn->value,
            FoundationPermission::NotificationsReadOwn->value,
            FoundationPermission::NotificationsViewOwn->value,
        ])
        ->and($platformAdmin->permissions)->toBeEmpty();
});
