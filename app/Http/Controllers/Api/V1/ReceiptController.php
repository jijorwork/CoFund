<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Actions\PrepareReceiptReissue;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Collections\Queries\FindReceiptByUuid;
use App\Domain\Collections\Services\BuildContributionReceiptView;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListReceiptsRequest;
use App\Http\Resources\Api\V1\ReceiptResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReceiptController extends Controller
{
    public function index(ListReceiptsRequest $request, TenantContext $context): AnonymousResourceCollection
    {
        $filters = $request->validated('filter', []);
        $query = Receipt::query()
            ->whereBelongsTo($context->organisation())
            ->with(['member', 'payment', 'loanRepayment', 'welfareBenefitPayment', 'withdrawalPayout', 'financialTransaction'])
            ->latest('issued_at');

        if ($context->roleAssignment()->branch_id !== null) {
            $query->where('branch_id', $context->branch()?->getKey());
        }
        if ($context->role()->name === 'collector') {
            $query->whereHas('payment', fn (Builder $query) => $query->where(
                'collected_by_user_id',
                $context->roleAssignment()->user_id,
            ));
        }
        foreach (['receipt_number', 'status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['member_uuid'])) {
            $query->where('member_id', Member::query()->where('public_id', $filters['member_uuid'])->value('id') ?? 0);
        }
        if (isset($filters['payment_uuid'])) {
            $query->whereHas('payment', fn (Builder $query) => $query->where('public_id', $filters['payment_uuid']));
        }
        $query->when($filters['issued_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '>=', $date))
            ->when($filters['issued_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issued_at', '<=', $date));

        return ReceiptResource::collection($query->paginate(25)->withQueryString())
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function show(Request $request, string $receiptUuid, FindReceiptByUuid $receipts): ReceiptResource
    {
        return ReceiptResource::make($receipts->handle($receiptUuid) ?? abort(404))
            ->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function printData(
        Request $request,
        string $receiptUuid,
        FindReceiptByUuid $receipts,
        BuildContributionReceiptView $builder,
        RoleAuthorization $authorization,
    ): JsonResponse {
        $authorization->authorize(FoundationPermission::ReceiptsView->value);

        return ApiResponse::success(
            $request,
            $builder->build($receipts->handle($receiptUuid) ?? abort(404)),
        );
    }

    public function reissue(
        Request $request,
        string $receiptUuid,
        FindReceiptByUuid $receipts,
        PrepareReceiptReissue $reissue,
    ): ReceiptResource {
        $prepared = $reissue->handle(
            $receipts->handle($receiptUuid) ?? abort(404),
        );

        return ReceiptResource::make($prepared['receipt'])
            ->additional(['meta' => [
                'request_id' => ApiResponse::requestId($request),
                'reissued_at' => $prepared['reissued_at']->toIso8601String(),
            ]]);
    }
}
