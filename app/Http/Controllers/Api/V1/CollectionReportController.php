<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reconciliation\Models\CashDepositBatch;
use App\Domain\Reconciliation\Models\CollectorCashHandover;
use App\Domain\Reconciliation\Models\CollectorCashVariance;
use App\Domain\Reconciliation\Models\CollectorCollectionSession;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionReportController extends Controller
{
    public function __construct(private RoleAuthorization $authorization, private TenantContext $context) {}

    public function collectorCollections(Request $request): JsonResponse
    {
        return $this->report($request, $this->scope(CollectorCollectionSession::query()->whereBelongsTo($this->context->organisation()), $request, 'session_date')
            ->selectRaw('COUNT(*) as session_count, COALESCE(SUM(expected_cash_minor),0) as expected_cash_minor, COALESCE(SUM(declared_cash_minor),0) as declared_cash_minor, COALESCE(SUM(shortage_minor),0) as shortage_minor, COALESCE(SUM(overage_minor),0) as overage_minor')->first()?->toArray() ?? []);
    }

    public function cashHandovers(Request $request): JsonResponse
    {
        return $this->report($request, $this->scope(CollectorCashHandover::query()->whereBelongsTo($this->context->organisation()), $request, 'handed_over_at')
            ->selectRaw('COUNT(*) as handover_count, COALESCE(SUM(accepted_cash_minor),0) as accepted_cash_minor, COALESCE(SUM(shortage_minor),0) as shortage_minor, COALESCE(SUM(overage_minor),0) as overage_minor')->first()?->toArray() ?? []);
    }

    public function cashVariances(Request $request): JsonResponse
    {
        return $this->report($request, $this->scope(CollectorCashVariance::query()->whereBelongsTo($this->context->organisation()), $request, 'reported_at')
            ->selectRaw('COUNT(*) as variance_count, COALESCE(SUM(amount_minor),0) as amount_minor')->first()?->toArray() ?? []);
    }

    public function cashDeposits(Request $request): JsonResponse
    {
        return $this->report($request, $this->scope(CashDepositBatch::query()->whereBelongsTo($this->context->organisation()), $request, 'deposit_date')
            ->selectRaw('COUNT(*) as deposit_count, COALESCE(SUM(amount_minor),0) as amount_minor')->first()?->toArray() ?? []);
    }

    public function cashPosition(Request $request, CalculateWalletBalance $balances): JsonResponse
    {
        $wallets = Wallet::query()->whereBelongsTo($this->context->organisation())
            ->whereIn('wallet_type', [WalletType::CollectorCash, WalletType::OfficeCash, WalletType::Bank, WalletType::MobileMoney])
            ->when($this->context->roleAssignment()->branch_id !== null, fn (Builder $query) => $query->where('branch_id', $this->context->branch()?->getKey()))
            ->with('ledgerAccount')->get();
        $rows = $wallets->map(fn (Wallet $wallet): array => [
            'wallet_uuid' => $wallet->public_id, 'wallet_type' => $wallet->wallet_type->value,
            'currency_code' => $wallet->currency_code, 'balance_minor' => $balances->calculate($wallet)->displayBalanceMinor,
        ])->all();

        return $this->report($request, ['wallets' => $rows]);
    }

    /** @param array<string, mixed> $data */
    private function report(Request $request, array $data): JsonResponse
    {
        $this->authorization->authorize(FoundationPermission::CollectionReportsView->value);

        return ApiResponse::success($request, $data);
    }

    /** @template T of \Illuminate\Database\Eloquent\Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function scope(Builder $query, Request $request, string $dateColumn): Builder
    {
        return $query
            ->when($this->context->roleAssignment()->branch_id !== null, fn (Builder $query) => $query->where('branch_id', $this->context->branch()?->getKey()))
            ->when($request->date('date_from'), fn (Builder $query, $date) => $query->whereDate($dateColumn, '>=', $date))
            ->when($request->date('date_to'), fn (Builder $query, $date) => $query->whereDate($dateColumn, '<=', $date));
    }
}
