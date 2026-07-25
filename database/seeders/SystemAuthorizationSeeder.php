<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SystemAuthorizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (FoundationPermission::cases() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }

        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::query()->firstOrCreate([
                'name' => $systemRole->value,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($this->permissionsFor($systemRole));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(SystemRole $role): array
    {
        return match ($role) {
            SystemRole::PlatformAdmin, SystemRole::Member => [],
            SystemRole::OrganisationAdmin => array_map(
                fn (FoundationPermission $permission): string => $permission->value,
                FoundationPermission::cases(),
            ),
            SystemRole::BranchManager => [
                FoundationPermission::BranchesView->value,
                FoundationPermission::OrganisationUsersView->value,
                FoundationPermission::RolesView->value,
                FoundationPermission::MembersView->value,
                FoundationPermission::MembersCreate->value,
                FoundationPermission::MembersUpdate->value,
                FoundationPermission::MembersSuspend->value,
                FoundationPermission::MembersViewDocuments->value,
                FoundationPermission::MembersActivate->value,
                FoundationPermission::MembersExit->value,
                FoundationPermission::MembersLinkUser->value,
                FoundationPermission::MembersManageContacts->value,
                FoundationPermission::MembersVerifyContacts->value,
                FoundationPermission::MembersUploadDocuments->value,
                FoundationPermission::MembersVerifyDocuments->value,
                FoundationPermission::GroupsView->value,
                FoundationPermission::GroupsCreate->value,
                FoundationPermission::GroupsUpdate->value,
                FoundationPermission::GroupsManageMembers->value,
                FoundationPermission::ContributionPlansView->value,
                FoundationPermission::ContributionPlansCreate->value,
                FoundationPermission::ContributionPlansUpdate->value,
                FoundationPermission::ContributionPlansActivate->value,
                FoundationPermission::ContributionPlansSuspend->value,
                FoundationPermission::ContributionPlansClose->value,
                FoundationPermission::ContributionEnrolmentsView->value,
                FoundationPermission::ContributionEnrolmentsCreate->value,
                FoundationPermission::ContributionEnrolmentsSuspend->value,
                FoundationPermission::ContributionEnrolmentsEnd->value,
                FoundationPermission::ContributionSchedulesView->value,
                FoundationPermission::ContributionSchedulesGenerate->value,
            ],
            SystemRole::Treasurer => [
                FoundationPermission::BranchesView->value,
                FoundationPermission::MembersView->value,
                FoundationPermission::GroupsView->value,
                FoundationPermission::ContributionPlansView->value,
                FoundationPermission::ContributionEnrolmentsView->value,
                FoundationPermission::ContributionEnrolmentsApprove->value,
                FoundationPermission::ContributionSchedulesView->value,
            ],
            SystemRole::Collector => [
                FoundationPermission::MembersView->value,
                FoundationPermission::GroupsView->value,
                FoundationPermission::ContributionPlansView->value,
                FoundationPermission::ContributionEnrolmentsView->value,
                FoundationPermission::ContributionSchedulesView->value,
            ],
            SystemRole::LoanOfficer,
            SystemRole::Approver => [
                FoundationPermission::MembersView->value,
            ],
            SystemRole::Auditor => [
                FoundationPermission::OrganisationsView->value,
                FoundationPermission::BranchesView->value,
                FoundationPermission::OrganisationUsersView->value,
                FoundationPermission::RolesView->value,
                FoundationPermission::MembersView->value,
                FoundationPermission::MembersViewDocuments->value,
                FoundationPermission::GroupsView->value,
                FoundationPermission::ContributionPlansView->value,
                FoundationPermission::ContributionEnrolmentsView->value,
                FoundationPermission::ContributionSchedulesView->value,
            ],
        };
    }
}
