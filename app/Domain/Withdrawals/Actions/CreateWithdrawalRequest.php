<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Withdrawals\Enums\WithdrawalProductStatus;
use App\Domain\Withdrawals\Enums\WithdrawalSequenceType;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Models\WithdrawalReason;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\CalculateWithdrawableSavings;
use App\Domain\Withdrawals\Services\CalculateWithdrawalFee;
use App\Domain\Withdrawals\Services\WithdrawalNumberGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateWithdrawalRequest
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private CalculateWithdrawableSavings $withdrawable, private CalculateWithdrawalFee $fees, private WithdrawalNumberGenerator $numbers) {}

    public function handle(User $actor, Member $member, WithdrawalProduct $product, Wallet $wallet, ?WithdrawalReason $reason, int $amountMinor, ?string $reasonText = null, bool $fullBalance = false): WithdrawalRequest
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalRequestsCreate->value);
        $organisation = $this->context->organisation();
        $calculation = $this->withdrawable->calculate($member, $product, $wallet, $reason);
        if ($amountMinor <= 0 || $member->organisation_id !== $organisation->getKey() || $product->organisation_id !== $organisation->getKey() || $product->status !== WithdrawalProductStatus::Active || $wallet->organisation_id !== $organisation->getKey() || $amountMinor > $calculation['withdrawable_balance_minor'] || ($product->require_reason && $reason === null)) {
            throw new InvalidWithdrawalOperation('The withdrawal request is not eligible.');
        }
        $fee = $this->fees->calculate($product, $amountMinor);

        return DB::transaction(fn (): WithdrawalRequest => WithdrawalRequest::query()->create(['organisation_id' => $organisation->getKey(), 'branch_id' => $member->branch_id, 'group_id' => $product->group_id, 'request_number' => $this->numbers->next($organisation, WithdrawalSequenceType::Request, now()->year), 'member_id' => $member->getKey(), 'withdrawal_product_id' => $product->getKey(), 'withdrawal_reason_id' => $reason?->getKey(), 'savings_wallet_id' => $wallet->getKey(), 'requested_amount_minor' => $amountMinor, 'eligible_amount_minor' => $calculation['withdrawable_balance_minor'], 'fee_minor' => $fee['fee_minor'], 'net_payout_minor' => $fee['net_payout_minor'], 'currency_code' => $product->currency_code, 'request_date' => CarbonImmutable::today($organisation->timezone), 'reason_text' => $reasonText, 'is_full_balance' => $fullBalance, 'eligibility_snapshot' => $calculation, 'restriction_snapshot' => $calculation['calculation_breakdown'], 'created_by_user_id' => $actor->getKey()]));
    }
}
