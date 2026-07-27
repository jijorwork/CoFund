<?php

namespace App\Domain\Wallets\Services;

use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateWallet
{
    public function handle(
        Organisation $organisation,
        ?Branch $branch,
        Model $owner,
        WalletOwnerType $ownerType,
        WalletType $walletType,
        string $name,
        string $code,
        LedgerAccount $account,
        ?User $actor,
        bool $isSystemWallet = false,
    ): Wallet {
        if (
            $account->organisation_id !== $organisation->getKey()
            || strtoupper($account->currency_code) !== strtoupper($organisation->currency_code)
            || $account->purpose !== $walletType->accountPurpose()
            || ($branch !== null && $branch->organisation_id !== $organisation->getKey())
            || ! $this->ownerMatches($organisation, $branch, $owner, $ownerType)
        ) {
            throw new InvalidWalletOperation;
        }

        $activeKey = Wallet::activeOwnerKey(
            (int) $organisation->getKey(),
            $ownerType->value,
            (int) $owner->getKey(),
            $walletType,
            $organisation->currency_code,
        );

        return DB::transaction(function () use (
            $organisation,
            $branch,
            $owner,
            $ownerType,
            $walletType,
            $name,
            $code,
            $account,
            $actor,
            $isSystemWallet,
            $activeKey,
        ): Wallet {
            if (Wallet::query()->where('active_owner_key', $activeKey)->lockForUpdate()->exists()) {
                throw new InvalidWalletOperation('An active wallet of this type already exists for the owner.');
            }

            return Wallet::query()->create([
                'organisation_id' => $organisation->getKey(),
                'branch_id' => $branch?->getKey(),
                'walletable_type' => $ownerType->value,
                'walletable_id' => $owner->getKey(),
                'ledger_account_id' => $account->getKey(),
                'wallet_type' => $walletType,
                'name' => $name,
                'code' => strtoupper($code),
                'currency_code' => strtoupper($organisation->currency_code),
                'status' => WalletStatus::Active,
                'is_system_wallet' => $isSystemWallet,
                'opened_at' => now(),
                'created_by_user_id' => $actor?->getKey(),
                'active_owner_key' => $activeKey,
            ])->load(['branch', 'ledgerAccount', 'walletable']);
        });
    }

    private function ownerMatches(
        Organisation $organisation,
        ?Branch $branch,
        Model $owner,
        WalletOwnerType $ownerType,
    ): bool {
        return match ($ownerType) {
            WalletOwnerType::Organisation => $owner->is($organisation) && $branch === null,
            WalletOwnerType::Branch => $owner instanceof Branch
                && $owner->organisation_id === $organisation->getKey()
                && $branch?->is($owner),
            WalletOwnerType::Member => $owner->getAttribute('organisation_id') === $organisation->getKey()
                && $owner->getAttribute('branch_id') === $branch?->getKey(),
            WalletOwnerType::CollectorUser, WalletOwnerType::CustodianUser => $owner instanceof User && $branch !== null,
        };
    }
}
