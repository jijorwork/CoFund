<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Actions\ManageLoanApplication;
use App\Domain\Loans\Actions\ManageLoanApproval;
use App\Domain\Loans\Actions\ManageLoanCollateral;
use App\Domain\Loans\Actions\ManageLoanGuarantor;
use App\Domain\Loans\Actions\ManageLoanOffer;
use App\Domain\Loans\Enums\CollateralStatus;
use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Loans\Models\LoanApplication;
use App\Domain\Loans\Models\LoanCollateral;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Loans\Models\LoanOffer;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Loans\Services\EvaluateLoanEligibility;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoanApplicationRequest;
use App\Http\Requests\Api\V1\LoanDecisionRequest;
use App\Http\Resources\Api\V1\LoanApplicationResource;
use App\Http\Resources\Api\V1\LoanResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApplicationController extends Controller
{
    public function index(Request $request, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanApplicationsView->value);
        $query = LoanApplication::query()->whereBelongsTo($context->organisation())->with(['member', 'product', 'branch'])->when($context->branch(), fn ($query, $branch) => $query->where('branch_id', $branch->getKey()))->when($request->input('filter.status'), fn ($query, $status) => $query->where('workflow_status', $status))->when($request->input('filter.member_uuid'), fn ($query, $uuid) => $query->whereHas('member', fn ($member) => $member->where('public_id', $uuid)));

        return LoanApplicationResource::collection($query->latest()->paginate(min($request->integer('per_page', 25), 100)))->additional(['meta' => ['request_id' => ApiResponse::requestId($request)]]);
    }

    public function store(LoanApplicationRequest $request, TenantContext $context, ManageLoanApplication $action): LoanApplicationResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $member = Member::query()->whereBelongsTo($context->organisation())->where('public_id', $request->validated('member_uuid'))->firstOrFail();
        $product = LoanProduct::query()->whereBelongsTo($context->organisation())->where('public_id', $request->validated('product_uuid'))->firstOrFail();

        return LoanApplicationResource::make($action->create($actor, $member, $product, $request->safe()->except(['member_uuid', 'product_uuid']))->load(['member', 'product', 'branch']));
    }

    public function show(string $applicationUuid, TenantContext $context, RoleAuthorization $authorization): LoanApplicationResource
    {
        $authorization->authorize(FoundationPermission::LoanApplicationsView->value);

        return LoanApplicationResource::make($this->find($context, $applicationUuid)->load(['member', 'product', 'branch', 'guarantors', 'collateral', 'approvals', 'offers']));
    }

    public function update(LoanApplicationRequest $request, string $applicationUuid, TenantContext $context, ManageLoanApplication $action): LoanApplicationResource
    {
        return LoanApplicationResource::make($action->update(
            $this->find($context, $applicationUuid),
            $request->safe()->except(['member_uuid', 'product_uuid'])
        ));
    }

    public function eligibility(string $applicationUuid, TenantContext $context, RoleAuthorization $authorization, EvaluateLoanEligibility $eligibility): JsonResource
    {
        $authorization->authorize(FoundationPermission::LoanApplicationsView->value);

        return new JsonResource($eligibility->forApplication($this->find($context, $applicationUuid)->load(['member', 'product'])));
    }

    public function submit(LoanDecisionRequest $request, string $applicationUuid, TenantContext $context, ManageLoanApplication $action): LoanApplicationResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return LoanApplicationResource::make($action->submit($actor, $this->find($context, $applicationUuid), $request->boolean('override'), $request->validated('override_reason')));
    }

    public function cancel(LoanDecisionRequest $request, string $applicationUuid, TenantContext $context, ManageLoanApplication $action): LoanApplicationResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return LoanApplicationResource::make($action->cancel(
            $actor,
            $this->find($context, $applicationUuid),
            (string) $request->validated('reason')
        ));
    }

    public function decision(LoanDecisionRequest $request, string $applicationUuid, string $level, TenantContext $context, ManageLoanApproval $action): LoanApplicationResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $application = $this->find($context, $applicationUuid);

        return LoanApplicationResource::make($action->decide($actor, $application, $level, $level !== 'rejected', (int) ($request->validated('approved_principal_minor') ?? $application->requested_principal_minor), (int) ($request->validated('approved_term') ?? $application->requested_term), $request->validated('reason')));
    }

    public function issueOffer(Request $request, string $applicationUuid, TenantContext $context, ManageLoanOffer $action): JsonResource
    {
        $data = $request->validate(['first_repayment_date' => ['required', 'date'], 'expires_at' => ['required', 'date', 'after:now']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return new JsonResource($action->issue($actor, $this->find($context, $applicationUuid), CarbonImmutable::parse($data['first_repayment_date']), CarbonImmutable::parse($data['expires_at'])));
    }

    public function acceptOffer(Request $request, string $offerUuid, TenantContext $context, ManageLoanOffer $action): LoanResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $offer = LoanOffer::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $offerUuid)->firstOrFail();

        return LoanResource::make($action->accept($actor, $offer)->load(['member', 'product']));
    }

    public function offers(Request $request, string $applicationUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanOffersView->value);

        return JsonResource::collection(
            $this->find($context, $applicationUuid)->offers()->latest('version')->paginate(min($request->integer('per_page', 25), 100))
        );
    }

    public function showOffer(string $offerUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::LoanOffersView->value);

        return new JsonResource($this->findOffer($context, $offerUuid));
    }

    public function declineOffer(LoanDecisionRequest $request, string $offerUuid, TenantContext $context, ManageLoanOffer $action): JsonResource
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return new JsonResource($action->decline($actor, $this->findOffer($context, $offerUuid), (string) $request->validated('reason')));
    }

    public function addGuarantor(Request $request, string $applicationUuid, TenantContext $context, ManageLoanGuarantor $action): JsonResource
    {
        $data = $request->validate(['member_uuid' => ['required', 'uuid'], 'guaranteed_amount_minor' => ['required', 'integer', 'min:1']]);
        $member = Member::query()->whereBelongsTo($context->organisation())->where('public_id', $data['member_uuid'])->firstOrFail();

        return new JsonResource($action->invite($this->find($context, $applicationUuid), $member, $data['guaranteed_amount_minor']));
    }

    public function respondGuarantor(string $guarantorUuid, string $status, TenantContext $context, ManageLoanGuarantor $action): JsonResource
    {
        $guarantor = LoanGuarantor::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $guarantorUuid)->firstOrFail();

        return new JsonResource($action->respond($guarantor, GuarantorStatus::from($status)));
    }

    public function guarantors(Request $request, string $applicationUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanGuarantorsView->value);

        return JsonResource::collection(
            $this->find($context, $applicationUuid)->guarantors()->with('guarantor')->latest()->paginate(min($request->integer('per_page', 25), 100))
        );
    }

    public function showGuarantor(string $guarantorUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::LoanGuarantorsView->value);

        return new JsonResource($this->findGuarantor($context, $guarantorUuid)->load(['application', 'guarantor']));
    }

    public function revokeGuarantor(string $guarantorUuid, TenantContext $context, ManageLoanGuarantor $action): JsonResource
    {
        return new JsonResource($action->revoke($this->findGuarantor($context, $guarantorUuid)));
    }

    public function addCollateral(Request $request, string $applicationUuid, TenantContext $context, ManageLoanCollateral $action): JsonResource
    {
        $data = $request->validate(['collateral_type' => ['required', 'string', 'max:60'], 'description' => ['required', 'string'], 'owner_name' => ['required', 'string'], 'estimated_value_minor' => ['required', 'integer', 'min:1'], 'currency_code' => ['required', 'string', 'size:3'], 'identifier' => ['nullable', 'string', 'max:500'], 'location' => ['nullable', 'string'], 'valuation_date' => ['nullable', 'date'], 'valuation_notes' => ['nullable', 'string']]);

        return new JsonResource($action->create($this->find($context, $applicationUuid), $data));
    }

    public function reviewCollateral(Request $request, string $collateralUuid, string $status, TenantContext $context, ManageLoanCollateral $action): JsonResource
    {
        $data = $request->validate(['accepted_value_minor' => ['nullable', 'integer', 'min:1'], 'reason' => ['nullable', 'string']]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $collateral = LoanCollateral::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $collateralUuid)->firstOrFail();

        return new JsonResource($action->review($actor, $collateral, CollateralStatus::from($status), $data['accepted_value_minor'] ?? null, $data['reason'] ?? null));
    }

    public function updateCollateral(Request $request, string $collateralUuid, TenantContext $context, ManageLoanCollateral $action): JsonResource
    {
        $data = $request->validate(['collateral_type' => ['sometimes', 'string', 'max:60'], 'description' => ['sometimes', 'string'], 'owner_name' => ['sometimes', 'string'], 'estimated_value_minor' => ['sometimes', 'integer', 'min:1'], 'currency_code' => ['sometimes', 'string', 'size:3'], 'identifier' => ['sometimes', 'nullable', 'string', 'max:500'], 'location' => ['sometimes', 'nullable', 'string'], 'valuation_date' => ['sometimes', 'nullable', 'date'], 'valuation_notes' => ['sometimes', 'nullable', 'string']]);

        return new JsonResource($action->update($this->findCollateral($context, $collateralUuid), $data));
    }

    public function collateral(Request $request, string $applicationUuid, TenantContext $context, RoleAuthorization $authorization): AnonymousResourceCollection
    {
        $authorization->authorize(FoundationPermission::LoanCollateralView->value);

        return JsonResource::collection(
            $this->find($context, $applicationUuid)->collateral()->latest()->paginate(min($request->integer('per_page', 25), 100))
        );
    }

    public function showCollateral(string $collateralUuid, TenantContext $context, RoleAuthorization $authorization): JsonResource
    {
        $authorization->authorize(FoundationPermission::LoanCollateralView->value);

        return new JsonResource($this->findCollateral($context, $collateralUuid));
    }

    private function find(TenantContext $context, string $uuid): LoanApplication
    {
        return LoanApplication::query()->whereBelongsTo($context->organisation())->where('public_id', $uuid)->firstOrFail();
    }

    private function findGuarantor(TenantContext $context, string $uuid): LoanGuarantor
    {
        return LoanGuarantor::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }

    private function findCollateral(TenantContext $context, string $uuid): LoanCollateral
    {
        return LoanCollateral::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }

    private function findOffer(TenantContext $context, string $uuid): LoanOffer
    {
        return LoanOffer::query()->where('organisation_id', $context->organisation()->getKey())->where('public_id', $uuid)->firstOrFail();
    }
}
