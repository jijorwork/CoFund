<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Actions\ApproveWelfareClaim;
use App\Domain\Welfare\Actions\CreateWelfareClaim;
use App\Domain\Welfare\Actions\ManageWelfareClaimBeneficiary;
use App\Domain\Welfare\Actions\ManageWelfareClaimWorkflow;
use App\Domain\Welfare\Actions\SettleWelfareClaim;
use App\Domain\Welfare\Actions\SubmitWelfareClaim;
use App\Domain\Welfare\Actions\UpdateWelfareClaim;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Models\WelfareClaimBeneficiary;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Domain\Welfare\Models\WelfareEvent;
use App\Domain\Welfare\Services\EvaluateWelfareEligibility;
use App\Domain\Welfare\Services\RecalculateWelfareClaimPosition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WelfareClaimRequest;
use App\Http\Requests\Api\V1\WelfareFinancialRequest;
use App\Http\Resources\Api\V1\WelfareClaimResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class WelfareClaimController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);
        $query = WelfareClaim::query()->where('organisation_id', $context->organisation()->getKey())->with(['member', 'product', 'event'])->withCount('beneficiaries')->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()));
        foreach (['claim_number', 'status', 'eligibility_status', 'verification_status', 'approval_status', 'payment_status'] as $field) {
            $query->when($request->input('filter.'.$field), fn ($query, $value) => $query->where($field, $value));
        }

return WelfareClaimResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(WelfareClaimRequest $request, TenantContext $context, CreateWelfareClaim $action): WelfareClaimResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $enrolment = WelfareEnrolment::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('enrolment_uuid'))->firstOrFail();
        $event = WelfareEvent::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $request->validated('event_uuid'))->firstOrFail();

        return WelfareClaimResource::make($action->handle($actor, $enrolment->load('product'), $event->load('eventType'), $request->safe()->except(['enrolment_uuid', 'event_uuid']))->load(['member', 'product', 'event']));
    }

    public function show(string $claimUuid, TenantContext $context, RoleAuthorization $authorization): WelfareClaimResource
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);

        return WelfareClaimResource::make($this->find($context, $claimUuid)->load(['member', 'product', 'event', 'enrolment', 'beneficiaries', 'documents', 'approvals', 'liabilities', 'payments'])->loadCount('beneficiaries'));
    }

    public function update(WelfareClaimRequest $request, string $claimUuid, TenantContext $context, UpdateWelfareClaim $action): WelfareClaimResource
    {
        return WelfareClaimResource::make($action->handle($this->find($context, $claimUuid)->load(['member', 'product', 'event', 'eventType', 'enrolment']), $request->safe()->except(['enrolment_uuid', 'event_uuid']))->load(['member', 'product', 'event']));
    }

    public function eligibility(string $claimUuid, TenantContext $context, RoleAuthorization $authorization, EvaluateWelfareEligibility $service): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);

        return new JsonResource($service->evaluate($this->find($context, $claimUuid)->load(['member', 'product', 'event', 'eventType', 'enrolment'])));
    }

    public function position(string $claimUuid, TenantContext $context, RoleAuthorization $authorization, RecalculateWelfareClaimPosition $service): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareClaimsView->value);

        return new JsonResource($service->recalculate($this->find($context, $claimUuid)));
    }

    public function submit(Request $request, string $claimUuid, TenantContext $context, SubmitWelfareClaim $action): WelfareClaimResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return WelfareClaimResource::make($action->handle($actor, $this->find($context, $claimUuid), $request->boolean('override'), $request->input('reason'))->load(['member', 'product', 'event']));
    }

    public function workflow(Request $request, string $claimUuid, string $transition, TenantContext $context, ManageWelfareClaimWorkflow $action): WelfareClaimResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $claim = $this->find($context, $claimUuid);
        $reason = $request->string('reason')->toString();
        $result = match ($transition) {
            'review' => $action->review($actor, $claim, $reason),
            'verify' => $action->verify($actor, $claim, true, $reason),
            'fail-verification' => $action->verify($actor, $claim, false, $reason),
            'reject' => $action->reject($actor, $claim, $reason),
            'cancel' => $action->cancel($actor, $claim, $reason),
            default => throw new \InvalidArgumentException('Unsupported welfare claim transition.'),
        };

        return WelfareClaimResource::make($result->load(['member', 'product', 'event']));
    }

    public function approve(WelfareFinancialRequest $request, string $claimUuid, string $level, TenantContext $context, ApproveWelfareClaim $action): WelfareClaimResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return WelfareClaimResource::make($action->handle($actor, $this->find($context, $claimUuid), $request->integer('approved_benefit_minor'), $level, CarbonImmutable::parse($request->validated('date')), $request->idempotencyKey(), $request->input('reason'), $request->boolean('override'))->load(['member', 'product', 'event']));
    }

    public function settle(string $claimUuid, TenantContext $context, SettleWelfareClaim $action): WelfareClaimResource
    {
        return WelfareClaimResource::make($action->handle($this->find($context, $claimUuid))->load(['member', 'product', 'event']));
    }

    public function beneficiaries(Request $request, string $claimUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::WelfareBeneficiariesView->value);

        return JsonResource::collection($this->find($context, $claimUuid)->beneficiaries()->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function addBeneficiary(Request $request, string $claimUuid, TenantContext $context, ManageWelfareClaimBeneficiary $action): JsonResource
    {
        return new JsonResource($action->create($this->find($context, $claimUuid), $this->beneficiaryData($request)));
    }

    public function showBeneficiary(string $beneficiaryUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::WelfareBeneficiariesView->value);

        return new JsonResource($this->findBeneficiary($context, $beneficiaryUuid));
    }

    public function updateBeneficiary(Request $request, string $beneficiaryUuid, TenantContext $context, ManageWelfareClaimBeneficiary $action): JsonResource
    {
        return new JsonResource($action->update($this->findBeneficiary($context, $beneficiaryUuid)->load('claim'), $this->beneficiaryData($request, false)));
    }

    /** @return array<string,mixed> */
    private function beneficiaryData(Request $request, bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return $request->validate(['beneficiary_type' => [$presence, 'string', 'max:40'], 'name' => [$presence, 'string'], 'relationship_to_member' => ['nullable', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'identification_type' => ['nullable', 'string'], 'identification_reference' => ['nullable', 'string'], 'allocation_basis_points' => ['nullable', 'integer', 'between:0,10000'], 'allocated_benefit_minor' => ['nullable', 'integer', 'min:0'], 'is_primary' => ['boolean'], 'payment_eligible' => ['boolean']]);
    }

    private function find(TenantContext $context, string $uuid): WelfareClaim
    {
        return WelfareClaim::query()->where('organisation_id', $context->organisation()->getKey())->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->where('public_id', $uuid)->firstOrFail();
    }

    private function findBeneficiary(TenantContext $context, string $uuid): WelfareClaimBeneficiary
    {
        return WelfareClaimBeneficiary::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
