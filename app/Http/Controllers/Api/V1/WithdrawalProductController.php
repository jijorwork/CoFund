<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Withdrawals\Actions\CreateWithdrawalProduct;
use App\Domain\Withdrawals\Actions\ManageWithdrawalProduct;
use App\Domain\Withdrawals\Enums\WithdrawalProductStatus;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WithdrawalProductRequest;
use App\Http\Resources\Api\V1\WithdrawalProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WithdrawalProductController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WithdrawalProductsView->value);
        $query = WithdrawalProduct::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branch->getKey())));
        foreach (['status', 'product_type', 'eligible_savings_wallet_type', 'currency_code'] as $field) {
            $query->when($request->input('filter.'.$field), fn ($query, $value) => $query->where($field, $value));
        }

        return WithdrawalProductResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(WithdrawalProductRequest $request, CreateWithdrawalProduct $action): WithdrawalProductResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return WithdrawalProductResource::make($action->handle($actor, $request->validated()));
    }

    public function show(string $productUuid, TenantContext $context, RoleAuthorization $authorization): WithdrawalProductResource
    {
        $authorization->authorize(FoundationPermission::WithdrawalProductsView->value);

        return WithdrawalProductResource::make($this->find($context, $productUuid));
    }

    public function update(WithdrawalProductRequest $request, string $productUuid, TenantContext $context, ManageWithdrawalProduct $action): WithdrawalProductResource
    {
        return WithdrawalProductResource::make($action->update($this->find($context, $productUuid), $request->validated()));
    }

    public function transition(string $productUuid, string $transition, TenantContext $context, ManageWithdrawalProduct $action): WithdrawalProductResource
    {
        $status = match ($transition) {
            'activate', 'reinstate' => WithdrawalProductStatus::Active,
            'suspend' => WithdrawalProductStatus::Suspended,
            'close' => WithdrawalProductStatus::Closed,
            'archive' => WithdrawalProductStatus::Archived,
            default => throw new \InvalidArgumentException('Unsupported withdrawal product transition.'),
        };

        return WithdrawalProductResource::make($action->transition($this->find($context, $productUuid), $status));
    }

    private function find(TenantContext $context, string $uuid): WithdrawalProduct
    {
        return WithdrawalProduct::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
