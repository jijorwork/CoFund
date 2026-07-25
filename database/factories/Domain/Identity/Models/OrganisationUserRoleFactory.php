<?php

namespace Database\Factories\Domain\Identity\Models;

use App\Domain\Identity\Enums\RoleAssignmentStatus;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationUserRole>
 */
class OrganisationUserRoleFactory extends Factory
{
    protected $model = OrganisationUserRole::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'branch_id' => null,
            'user_id' => User::factory(),
            'role_id' => fn (): int => Role::query()->firstOrCreate([
                'name' => SystemRole::Member->value,
                'guard_name' => 'web',
            ])->getKey(),
            'status' => RoleAssignmentStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
            'active_scope_key' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrganisationUserRole $assignment): void {
            OrganisationUser::query()->firstOrCreate([
                'organisation_id' => $assignment->organisation_id,
                'user_id' => $assignment->user_id,
            ], [
                'status' => 'active',
                'joined_at' => now(),
            ]);

            if ($assignment->status === RoleAssignmentStatus::Active) {
                $assignment->active_scope_key = OrganisationUserRole::activeScopeKey(
                    $assignment->organisation,
                    $assignment->user,
                    $assignment->role,
                    $assignment->branch,
                );
            }
        });
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => RoleAssignmentStatus::Pending,
            'active_scope_key' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => RoleAssignmentStatus::Suspended,
            'active_scope_key' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => RoleAssignmentStatus::Revoked,
            'revoked_at' => now(),
            'active_scope_key' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['ends_at' => now()->subMinute()]);
    }

    public function futureDated(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->addDay()]);
    }

    public function organisationWide(): static
    {
        return $this->state(fn (): array => ['branch_id' => null]);
    }

    public function branchSpecific(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'branch_id' => Branch::factory()->state([
                    'organisation_id' => $attributes['organisation_id'],
                ]),
            ];
        });
    }
}
