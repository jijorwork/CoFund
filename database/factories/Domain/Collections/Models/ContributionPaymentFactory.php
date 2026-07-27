<?php

namespace Database\Factories\Domain\Collections\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContributionPayment> */
class ContributionPaymentFactory extends Factory
{
    protected $model = ContributionPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $member = Member::factory()->active()->create();
        $plan = ContributionPlan::factory()->for($member->organisation)->create();
        $enrolment = MemberPlanEnrolment::factory()
            ->for($plan, 'contributionPlan')
            ->for($member)
            ->create();
        $sourceAccount = LedgerAccount::factory()
            ->for($member->organisation)
            ->category(AccountCategory::Asset)
            ->create(['purpose' => AccountPurpose::Cash]);
        $destinationAccount = LedgerAccount::factory()
            ->for($member->organisation)
            ->category(AccountCategory::Liability)
            ->create(['purpose' => AccountPurpose::MemberSavingsLiability]);
        $source = Wallet::factory()->create([
            'organisation_id' => $member->organisation_id,
            'walletable_type' => 'organisation',
            'walletable_id' => $member->organisation_id,
            'ledger_account_id' => $sourceAccount->getKey(),
            'wallet_type' => WalletType::OfficeCash,
            'status' => WalletStatus::Active,
        ]);
        $destination = Wallet::factory()->create([
            'organisation_id' => $member->organisation_id,
            'walletable_type' => 'member',
            'walletable_id' => $member->getKey(),
            'ledger_account_id' => $destinationAccount->getKey(),
            'wallet_type' => WalletType::MemberSavings,
            'status' => WalletStatus::Active,
        ]);
        $transaction = FinancialTransaction::factory()
            ->for($member->organisation)
            ->create();

        return [
            'organisation_id' => $member->organisation_id,
            'branch_id' => $member->branch_id,
            'member_id' => $member->getKey(),
            'contribution_plan_id' => $plan->getKey(),
            'member_plan_enrolment_id' => $enrolment->getKey(),
            'financial_transaction_id' => $transaction->getKey(),
            'payment_number' => fake()->unique()->bothify('ORG-PAY-2026-######'),
            'payment_method' => ContributionPaymentMethod::Cash,
            'source_wallet_id' => $source->getKey(),
            'destination_wallet_id' => $destination->getKey(),
            'amount_minor' => 10000,
            'allocated_amount_minor' => 0,
            'unallocated_amount_minor' => 10000,
            'currency_code' => 'GHS',
            'payment_date' => now()->toDateString(),
            'received_at' => now(),
            'status' => ContributionPaymentStatus::Unallocated,
            'collected_by_user_id' => User::factory(),
            'idempotency_key' => fake()->unique()->uuid(),
            'request_fingerprint' => hash('sha256', fake()->uuid()),
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (): array => ['status' => ContributionPaymentStatus::Posted]);
    }

    public function fullyAllocated(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ContributionPaymentStatus::Allocated, 'allocated_amount_minor' => $attributes['amount_minor'], 'unallocated_amount_minor' => 0]);
    }

    public function partiallyAllocated(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ContributionPaymentStatus::PartiallyAllocated, 'allocated_amount_minor' => intdiv((int) $attributes['amount_minor'], 2), 'unallocated_amount_minor' => (int) $attributes['amount_minor'] - intdiv((int) $attributes['amount_minor'], 2)]);
    }

    public function unallocated(): static
    {
        return $this;
    }

    public function cash(): static
    {
        return $this->state(fn (): array => ['payment_method' => ContributionPaymentMethod::Cash]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (): array => ['payment_method' => ContributionPaymentMethod::BankTransfer]);
    }

    public function manualMobileMoney(): static
    {
        return $this->state(fn (): array => ['payment_method' => ContributionPaymentMethod::MobileMoneyManual]);
    }

    public function collectorPayment(): static
    {
        return $this->state(fn (array $attributes): array => ['collector_wallet_id' => $attributes['source_wallet_id']]);
    }

    public function officePayment(): static
    {
        return $this->cash();
    }

    public function reversed(): static
    {
        return $this->state(fn (): array => ['status' => ContributionPaymentStatus::Reversed]);
    }
}
