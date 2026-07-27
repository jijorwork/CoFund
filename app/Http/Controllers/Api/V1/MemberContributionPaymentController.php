<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Services\CalculateMemberUnallocatedContributionBalance;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Queries\FindMemberByUuid;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Services\CalculateMemberWalletBalances;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContributionPaymentResource;
use App\Http\Responses\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberContributionPaymentController extends Controller
{
    public function index(
        Request $request,
        string $memberUuid,
        FindMemberByUuid $members,
        RoleAuthorization $authorization,
    ): AnonymousResourceCollection {
        $authorization->authorize(FoundationPermission::ContributionPaymentsView->value);
        $member = $members->handle($memberUuid) ?? abort(404);
        $payments = ContributionPayment::query()
            ->whereBelongsTo($member)
            ->with(['member', 'contributionPlan', 'enrolment', 'receipt', 'collectedBy', 'reversalPayment'])
            ->latest('payment_date')
            ->paginate(25);

        return ContributionPaymentResource::collection($payments)
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function position(
        Request $request,
        string $memberUuid,
        FindMemberByUuid $members,
        RoleAuthorization $authorization,
        CalculateMemberUnallocatedContributionBalance $unallocated,
        CalculateMemberWalletBalances $wallets,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ContributionPaymentsView->value);
        $member = $members->handle($memberUuid) ?? abort(404);
        $today = CarbonImmutable::now($member->organisation->timezone)->startOfDay();
        $obligations = ContributionObligation::query()
            ->whereBelongsTo($member)
            ->whereNotIn('schedule_status', [ObligationStatus::Cancelled, ObligationStatus::Waived]);
        $expectedToDate = (clone $obligations)->whereDate('due_date', '<=', $today)->sum('expected_amount_minor');
        $satisfiedToDate = (clone $obligations)->whereDate('due_date', '<=', $today)->sum('satisfied_amount_minor');
        $walletBalance = collect($wallets->calculate($member))
            ->first(fn (array $row): bool => $row['wallet']->wallet_type === WalletType::MemberSavings);

        return ApiResponse::success($request, [
            'total_posted_contribution_payments_minor' => (int) ContributionPayment::query()
                ->whereBelongsTo($member)
                ->whereNull('reversed_payment_id')
                ->whereNot('status', 'reversed')
                ->sum('amount_minor'),
            'total_active_allocated_contributions_minor' => (int) $obligations->sum('satisfied_amount_minor'),
            'total_unallocated_contribution_amount_minor' => $unallocated->calculate($member),
            'expected_to_date_minor' => (int) $expectedToDate,
            'satisfied_to_date_minor' => (int) $satisfiedToDate,
            'outstanding_expected_contributions_minor' => max(0, (int) $expectedToDate - (int) $satisfiedToDate),
            'overdue_obligation_count' => (clone $obligations)
                ->whereDate('grace_due_date', '<', $today)
                ->where('outstanding_amount_minor', '>', 0)
                ->count(),
            'next_due_date' => (clone $obligations)
                ->whereDate('due_date', '>=', $today)
                ->where('outstanding_amount_minor', '>', 0)
                ->orderBy('due_date')
                ->value('due_date'),
            'member_savings_wallet_balance_minor' => $walletBalance['balance']->displayBalanceMinor ?? 0,
            'currency_code' => $member->organisation->currency_code,
        ]);
    }
}
