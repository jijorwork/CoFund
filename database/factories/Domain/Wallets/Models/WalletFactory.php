<?php

namespace Database\Factories\Domain\Wallets\Models;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Membership\Models\Member;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Wallet> */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'walletable_type' => 'member',
            'walletable_id' => Member::factory(),
            'organisation_id' => fn (array $attributes): int => Member::query()
                ->findOrFail((int) $attributes['walletable_id'])
                ->organisation_id,
            'branch_id' => fn (array $attributes): ?int => Member::query()
                ->findOrFail((int) $attributes['walletable_id'])
                ->branch_id,
            'ledger_account_id' => function (array $attributes): LedgerAccount {
                $member = Member::query()->findOrFail((int) $attributes['walletable_id']);

                return LedgerAccount::factory()
                    ->for($member->organisation)
                    ->category(AccountCategory::Liability)
                    ->create(['purpose' => AccountPurpose::MemberSavingsLiability]);
            },
            'wallet_type' => WalletType::MemberSavings,
            'name' => 'Member Savings',
            'code' => fake()->unique()->bothify('WALLET-######'),
            'currency_code' => 'GHS',
            'status' => WalletStatus::Active,
            'is_system_wallet' => false,
            'opened_at' => now(),
            'active_owner_key' => null,
        ];
    }

    public function memberSavings(): static
    {
        return $this;
    }

    public function officeCash(): static
    {
        return $this->state(fn (): array => ['wallet_type' => WalletType::OfficeCash]);
    }

    public function collectorCash(): static
    {
        return $this->state(fn (): array => ['wallet_type' => WalletType::CollectorCash]);
    }

    public function bank(): static
    {
        return $this->state(fn (): array => ['wallet_type' => WalletType::Bank]);
    }

    public function mobileMoney(): static
    {
        return $this->state(fn (): array => ['wallet_type' => WalletType::MobileMoney]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => WalletStatus::Suspended]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => WalletStatus::Closed, 'closed_at' => now()]);
    }
}
