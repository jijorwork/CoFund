<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Enums\SavingsHoldStatus;
use App\Domain\Withdrawals\Enums\WithdrawalSequenceType;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\SavingsHold;
use App\Domain\Withdrawals\Services\WithdrawalNumberGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageSavingsHold
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private WithdrawalNumberGenerator $numbers) {}

    public function create(User $actor, Member $member, Wallet $wallet, string $type, int $amountMinor, string $reason, string $sourceType = 'manual', ?string $sourceUuid = null): SavingsHold
    {
        $this->authorization->authorize(FoundationPermission::SavingsHoldsCreate->value);
        $organisation = $this->context->organisation();
        if ($amountMinor <= 0 || trim($reason) === '' || $member->organisation_id !== $organisation->getKey() || $wallet->organisation_id !== $organisation->getKey() || $wallet->walletable_type !== 'member' || $wallet->walletable_id !== $member->getKey() || $wallet->wallet_type !== WalletType::MemberSavings) {
            throw new InvalidWithdrawalOperation('The savings hold is invalid.');
        }

        return DB::transaction(fn (): SavingsHold => SavingsHold::query()->create(['organisation_id' => $organisation->getKey(), 'branch_id' => $member->branch_id, 'member_id' => $member->getKey(), 'wallet_id' => $wallet->getKey(), 'hold_number' => $this->numbers->next($organisation, WithdrawalSequenceType::Hold, now()->year), 'hold_type' => $type, 'source_type' => $sourceType, 'source_uuid' => $sourceUuid, 'amount_minor' => $amountMinor, 'currency_code' => $wallet->currency_code, 'reason' => $reason, 'starts_at' => now(), 'created_by_user_id' => $actor->getKey()]));
    }

    public function release(User $actor, SavingsHold $hold, string $reason): SavingsHold
    {
        $this->authorization->authorize(FoundationPermission::SavingsHoldsRelease->value);
        if ($hold->status !== SavingsHoldStatus::Active || trim($reason) === '') {
            throw new InvalidWithdrawalOperation('The active hold and a release reason are required.');
        }
        $hold->update(['status' => SavingsHoldStatus::Released, 'released_at' => now(), 'released_by_user_id' => $actor->getKey(), 'release_reason' => $reason]);

        return $hold->refresh();
    }

    public function approve(User $actor, SavingsHold $hold): SavingsHold
    {
        $this->authorization->authorize(FoundationPermission::SavingsHoldsApprove->value);
        if ($hold->status !== SavingsHoldStatus::Active || $hold->approved_by_user_id !== null || $hold->created_by_user_id === $actor->getKey()) {
            throw new InvalidWithdrawalOperation('The hold requires approval by a different actor.');
        }
        $hold->update(['approved_by_user_id' => $actor->getKey()]);

        return $hold->refresh();
    }

    public function cancel(User $actor, SavingsHold $hold, string $reason): SavingsHold
    {
        $this->authorization->authorize(FoundationPermission::SavingsHoldsCancel->value);
        if ($hold->status !== SavingsHoldStatus::Active || trim($reason) === '') {
            throw new InvalidWithdrawalOperation('Only an active hold may be cancelled with a reason.');
        }
        $hold->update(['status' => SavingsHoldStatus::Cancelled, 'released_at' => now(), 'released_by_user_id' => $actor->getKey(), 'release_reason' => $reason]);

        return $hold->refresh();
    }
}
