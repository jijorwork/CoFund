<?php

namespace Database\Factories\Domain\Membership\Models;

use App\Domain\Membership\Enums\MemberContactStatus;
use App\Domain\Membership\Enums\MemberContactType;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberContact> */
class MemberContactFactory extends Factory
{
    protected $model = MemberContact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'organisation_id' => fn (array $attributes): int => Member::query()->findOrFail((int) $attributes['member_id'])->organisation_id,
            'contact_type' => MemberContactType::NextOfKin,
            'full_name' => fake()->name(),
            'relationship' => 'Relative',
            'phone' => '+233'.fake()->numerify('#########'),
            'percentage_entitlement' => null,
            'is_primary' => false,
            'is_emergency_contact' => false,
            'status' => MemberContactStatus::Active,
            'primary_scope_key' => null,
        ];
    }

    public function beneficiary(string $percentage = '25.00'): static
    {
        return $this->state(fn (): array => [
            'contact_type' => MemberContactType::Beneficiary,
            'percentage_entitlement' => $percentage,
        ]);
    }

    public function nextOfKin(): static
    {
        return $this->state(fn (): array => ['contact_type' => MemberContactType::NextOfKin]);
    }

    public function emergencyContact(): static
    {
        return $this->state(fn (): array => [
            'contact_type' => MemberContactType::EmergencyContact,
            'is_emergency_contact' => true,
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true])
            ->afterMaking(function (MemberContact $contact): void {
                $contact->primary_scope_key = MemberContact::primaryScopeKey(
                    $contact->member,
                    $contact->contact_type,
                );
            });
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'verified_at' => now(),
            'verified_by_user_id' => User::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => MemberContactStatus::Inactive,
            'primary_scope_key' => null,
        ]);
    }
}
