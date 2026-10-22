<?php

namespace Database\Factories\Domain\Organisations\Models;

use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationStatusHistory>
 */
class OrganisationStatusHistoryFactory extends Factory
{
    protected $model = OrganisationStatusHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'from_status' => OrganisationStatus::Pending,
            'to_status' => OrganisationStatus::Active,
            'reason' => fake()->sentence(),
            'changed_by_user_id' => User::factory(),
            'metadata' => null,
        ];
    }
}
