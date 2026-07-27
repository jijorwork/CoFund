<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Actions\CreateWelfareEnrolment;
use App\Domain\Welfare\Actions\ManageWelfareEnrolment;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WelfareEnrolmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WelfareEnrolmentController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareEnrolmentsView->value);
        $query = WelfareEnrolment::query()->where('organisation_id', $context->organisation()->getKey())->with(['member', 'product'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->when($request->input('filter.status'), fn ($query, $value) => $query->where('status', $value));

        return WelfareEnrolmentResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(Request $request, TenantContext $context, CreateWelfareEnrolment $action): WelfareEnrolmentResource
    {
        $data = $request->validate(['member_uuid' => ['required', 'uuid'], 'product_uuid' => ['required', 'uuid'], 'coverage_start_date' => ['required', 'date']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = Member::query()->whereBelongsTo($context->organisation())->where('public_id', $data['member_uuid'])->firstOrFail();
        $product = WelfareProduct::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $data['product_uuid'])->firstOrFail();

        return WelfareEnrolmentResource::make($action->handle($actor, $member, $product, CarbonImmutable::parse($data['coverage_start_date']))->load(['member', 'product']));
    }

    public function show(string $enrolmentUuid, TenantContext $context, RoleAuthorization $authorization): WelfareEnrolmentResource
    {
        $authorization->authorize(FoundationPermission::WelfareEnrolmentsView->value);

        return WelfareEnrolmentResource::make($this->find($context, $enrolmentUuid)->load(['member', 'product']));
    }

    public function transition(Request $request, string $enrolmentUuid, string $action, TenantContext $context, ManageWelfareEnrolment $manager): WelfareEnrolmentResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $enrolment = $this->find($context, $enrolmentUuid)->load('product');
        $reason = (string) $request->input('reason');
        $result = match ($action) {
            'approve' => $manager->approve($actor, $enrolment),
            'activate' => $manager->activate($enrolment),
            'suspend' => $manager->suspend($enrolment, $reason),
            'reinstate' => $manager->reinstate($enrolment),
            'terminate' => $manager->terminate($enrolment, $reason),
            default => throw new \InvalidArgumentException('Unsupported welfare enrolment transition.'),
        };

        return WelfareEnrolmentResource::make($result->load(['member', 'product']));
    }

    private function find(TenantContext $context, string $uuid): WelfareEnrolment
    {
        return WelfareEnrolment::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }
}
