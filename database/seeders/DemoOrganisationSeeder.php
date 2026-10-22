<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Enums\OrganisationType;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\OrganisationSubscription;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoOrganisationSeeder extends Seeder
{
    public const PASSWORD = 'CofundDemo!2026';

    public function run(): void
    {
        $this->call(SystemAuthorizationSeeder::class);

        DB::transaction(function (): void {
            $adom = $this->organisation(
                code: 'ADOM-COOP',
                name: 'Adom Community Cooperative',
                type: OrganisationType::Cooperative,
                status: OrganisationStatus::Active,
                email: 'office@adom-coop.test',
            );
            $adomHeadOffice = $this->branch($adom, 'ADOM-HQ', 'Adom Head Office');
            $adomMarket = $this->branch($adom, 'ADOM-MKT', 'Central Market Branch');

            $this->organisationUser($adom, 'Ama Mensah', 'admin.adom@cofund.test', SystemRole::OrganisationAdmin);
            $this->organisationUser($adom, 'Kwame Asante', 'treasurer.adom@cofund.test', SystemRole::Treasurer);
            $this->organisationUser(
                $adom,
                'Akosua Boateng',
                'officer.adom@cofund.test',
                SystemRole::MembershipOfficer,
                $adomHeadOffice,
            );
            $this->organisationUser(
                $adom,
                'Yaw Ofori',
                'collector.adom@cofund.test',
                SystemRole::Collector,
                $adomMarket,
            );

            $unity = $this->organisation(
                code: 'UNITY-WEL',
                name: 'Unity Welfare Association',
                type: OrganisationType::Welfare,
                status: OrganisationStatus::Active,
                email: 'office@unity-welfare.test',
            );
            $unityHeadOffice = $this->branch($unity, 'UNITY-HQ', 'Unity Head Office');

            $this->organisationUser($unity, 'Esi Owusu', 'admin.unity@cofund.test', SystemRole::OrganisationAdmin);
            $this->organisationUser(
                $unity,
                'Kofi Addo',
                'welfare.unity@cofund.test',
                SystemRole::WelfareOfficer,
                $unityHeadOffice,
            );
            $this->organisationUser($unity, 'Abena Darko', 'auditor.unity@cofund.test', SystemRole::Auditor);

            $plan = SubscriptionPlan::query()->firstOrCreate(
                ['code' => 'STANDARD'],
                [
                    'name' => 'Standard',
                    'description' => 'Standard CoFund organisation subscription.',
                    'monthly_price_minor' => 25000,
                    'yearly_price_minor' => 250000,
                    'currency_code' => 'GHS',
                    'grace_days' => 7,
                    'limits' => null,
                    'is_active' => true,
                ],
            );
            $this->activeDemoSubscription($adom, $plan, User::query()->where('email', 'admin.adom@cofund.test')->sole());
            $this->activeDemoSubscription($unity, $plan, User::query()->where('email', 'admin.unity@cofund.test')->sole());

            $hope = $this->organisation(
                code: 'HOPE-SUSU',
                name: 'Hope Susu Group',
                type: OrganisationType::Susu,
                status: OrganisationStatus::Pending,
                email: 'office@hope-susu.test',
            );
            $applicant = $this->user('Nana Appiah', 'applicant.hope@cofund.test');

            OrganisationUser::query()->updateOrCreate(
                [
                    'organisation_id' => $hope->getKey(),
                    'user_id' => $applicant->getKey(),
                ],
                [
                    'status' => OrganisationAccessStatus::Invited,
                    'joined_at' => null,
                    'granted_by_user_id' => null,
                ],
            );
        });
    }

    private function organisation(
        string $code,
        string $name,
        OrganisationType $type,
        OrganisationStatus $status,
        string $email,
    ): Organisation {
        return Organisation::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'organisation_type' => $type,
                'phone' => '+233 30 200 0000',
                'email' => $email,
                'address' => 'Accra, Ghana',
                'currency_code' => 'GHS',
                'timezone' => 'Africa/Accra',
                'status' => $status,
            ],
        );
    }

    private function branch(Organisation $organisation, string $code, string $name): Branch
    {
        return Branch::query()->updateOrCreate(
            [
                'organisation_id' => $organisation->getKey(),
                'code' => $code,
            ],
            [
                'name' => $name,
                'phone' => '+233 30 200 0000',
                'email' => null,
                'address' => 'Accra, Ghana',
                'status' => BranchStatus::Active,
            ],
        );
    }

    private function organisationUser(
        Organisation $organisation,
        string $name,
        string $email,
        SystemRole $systemRole,
        ?Branch $branch = null,
    ): User {
        $user = $this->user($name, $email);

        OrganisationUser::query()->updateOrCreate(
            [
                'organisation_id' => $organisation->getKey(),
                'user_id' => $user->getKey(),
            ],
            [
                'status' => OrganisationAccessStatus::Active,
                'joined_at' => now(),
                'granted_by_user_id' => $user->getKey(),
            ],
        );

        $role = Role::query()
            ->where('name', $systemRole->value)
            ->where('guard_name', 'web')
            ->sole();
        $activeScopeKey = OrganisationUserRole::activeScopeKey($organisation, $user, $role, $branch);

        OrganisationUserRole::query()->updateOrCreate(
            ['active_scope_key' => $activeScopeKey],
            [
                'organisation_id' => $organisation->getKey(),
                'branch_id' => $branch?->getKey(),
                'user_id' => $user->getKey(),
                'role_id' => $role->getKey(),
                'status' => RoleAssignmentStatus::Active,
                'starts_at' => null,
                'ends_at' => null,
                'assigned_by_user_id' => $user->getKey(),
                'assigned_at' => now(),
                'revoked_by_user_id' => null,
                'revoked_at' => null,
                'revocation_reason' => null,
            ],
        );

        return $user;
    }

    private function user(string $name, string $email): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'email_verified_at' => now(),
            'password' => self::PASSWORD,
        ])->save();

        return $user;
    }

    private function activeDemoSubscription(
        Organisation $organisation,
        SubscriptionPlan $plan,
        User $creator,
    ): void {
        OrganisationSubscription::query()->firstOrCreate(
            ['active_scope_key' => (string) $organisation->getKey()],
            [
                'organisation_id' => $organisation->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_interval' => BillingInterval::Yearly,
                'status' => SubscriptionStatus::Active,
                'starts_on' => today(),
                'current_period_starts_on' => today(),
                'current_period_ends_on' => today()->addYear()->subDay(),
                'grace_ends_on' => today()->addYear()->addDays($plan->grace_days)->subDay(),
                'created_by_user_id' => $creator->getKey(),
                'metadata' => ['source' => 'demo_seeder'],
            ],
        );
    }
}
