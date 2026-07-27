<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Actions\ManageLoanProduct;
use App\Domain\Loans\Enums\LoanProductStatus;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoanProductRequest;
use App\Http\Resources\Api\V1\LoanProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoanProductController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanProductsView->value);
        $query = LoanProduct::query()->whereBelongsTo($context->organisation())->with('branch')->when($context->branch(), fn ($query, $branch) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branch->getKey())))->when($request->input('filter.status'), fn ($query, $status) => $query->where('status', $status));

        return LoanProductResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(LoanProductRequest $request, TenantContext $context, ManageLoanProduct $action): LoanProductResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $data = $this->resolveScope($request->validated(), $context);

        return LoanProductResource::make($action->create($actor, $data)->load('branch'));
    }

    public function show(string $productUuid, TenantContext $context, RoleAuthorization $authorization): LoanProductResource
    {
        $authorization->authorize(FoundationPermission::LoanProductsView->value);

        return LoanProductResource::make($this->find($context, $productUuid)->load('branch'));
    }

    public function update(LoanProductRequest $request, string $productUuid, TenantContext $context, ManageLoanProduct $action): LoanProductResource
    {
        return LoanProductResource::make($action->update($this->find($context, $productUuid), $this->resolveScope($request->validated(), $context))->load('branch'));
    }

    public function transition(string $productUuid, string $status, TenantContext $context, ManageLoanProduct $action): LoanProductResource
    {
        return LoanProductResource::make($action->transition($this->find($context, $productUuid), LoanProductStatus::from($status)));
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function resolveScope(array $data, TenantContext $context): array
    {
        $data['branch_id'] = isset($data['branch_uuid']) ? Branch::query()->whereBelongsTo($context->organisation())->where('public_id', $data['branch_uuid'])->value('id') : null;
        $data['group_id'] = isset($data['group_uuid']) ? Group::query()->whereBelongsTo($context->organisation())->where('public_id', $data['group_uuid'])->value('id') : null;
        unset($data['branch_uuid'], $data['group_uuid']);

        return $data;
    }

    private function find(TenantContext $context, string $uuid): LoanProduct
    {
        return LoanProduct::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)->firstOrFail();
    }
}
