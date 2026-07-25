<?php

namespace Database\Factories\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\OrganisationAccessStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationUser>
 */
class OrganisationUserFactory extends Factory
{
    protected $model = OrganisationUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'status' => OrganisationAccessStatus::Active,
            'joined_at' => now(),
        ];
    }

    public function invited(): static
    {
        return $this->state(fn (): array => [
            'status' => OrganisationAccessStatus::Invited,
            'joined_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => OrganisationAccessStatus::Suspended]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => OrganisationAccessStatus::Revoked]);
    }
}
