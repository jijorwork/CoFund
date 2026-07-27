<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Actions\ChangeWelfareProductStatus;
use App\Domain\Welfare\Actions\CreateWelfareProduct;
use App\Domain\Welfare\Actions\UpdateWelfareProduct;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WelfareProductRequest;
use App\Http\Resources\Api\V1\WelfareProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WelfareProductController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareProductsView->value);
        $query = WelfareProduct::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branch->getKey())));
        foreach (['status', 'product_type', 'benefit_calculation_type', 'currency_code'] as $field) {
            $query->when($request->input('filter.'.$field), fn ($query, $value) => $query->where($field, $value));
        }

        return WelfareProductResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(WelfareProductRequest $request, TenantContext $context, CreateWelfareProduct $action): WelfareProductResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $data = $request->safe()->except(['branch_uuid', 'group_uuid']);
        $data['branch_id'] = $request->validated('branch_uuid') === null ? null : Branch::query()->whereBelongsTo($context->organisation())->where('public_id', $request->validated('branch_uuid'))->value('id');
        $data['group_id'] = $request->validated('group_uuid') === null ? null : Group::query()->whereBelongsTo($context->organisation())->where('public_id', $request->validated('group_uuid'))->value('id');

        return WelfareProductResource::make($action->handle($actor, $data));
    }

    public function show(string $productUuid, TenantContext $context, RoleAuthorization $authorization): WelfareProductResource
    {
        $authorization->authorize(FoundationPermission::WelfareProductsView->value);

        return WelfareProductResource::make($this->find($context, $productUuid));
    }

    public function update(WelfareProductRequest $request, string $productUuid, TenantContext $context, UpdateWelfareProduct $action): WelfareProductResource
    {
        return WelfareProductResource::make($action->handle($this->find($context, $productUuid), $request->safe()->except(['branch_uuid', 'group_uuid'])));
    }

    public function transition(string $productUuid, string $status, TenantContext $context, ChangeWelfareProductStatus $action): WelfareProductResource
    {
        $target = match ($status) {
            'activate', 'reinstate' => WelfareProductStatus::Active,
            'suspend' => WelfareProductStatus::Suspended,
            'close' => WelfareProductStatus::Closed,
            'archive' => WelfareProductStatus::Archived,
            default => throw new \InvalidArgumentException('Unsupported welfare product transition.'),
        };

        return WelfareProductResource::make($action->handle($this->find($context, $productUuid), $target));
    }

    private function find(TenantContext $context, string $uuid): WelfareProduct
    {
        return WelfareProduct::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
