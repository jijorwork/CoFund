<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Enums\WelfareApprovalStatus;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareVerificationStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Services\EvaluateWelfareEligibility;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ApproveWelfareClaim
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private EvaluateWelfareEligibility $eligibility, private RecogniseApprovedWelfareClaim $recognise) {}

    public function handle(User $actor, WelfareClaim $claim, int $approvedMinor, string $level, CarbonImmutable $date, string $idempotencyKey, ?string $reason = null, bool $override = false): WelfareClaim
    {
        $permission = $level === 'secondary' ? FoundationPermission::WelfareClaimsSecondaryApprove : FoundationPermission::WelfareClaimsApprove;
        $this->authorization->authorize($permission->value);

        return DB::transaction(function () use ($actor, $claim, $approvedMinor, $level, $date, $idempotencyKey, $reason, $override): WelfareClaim {
            $locked = WelfareClaim::query()->with(['product', 'member', 'enrolment', 'event', 'eventType', 'approvals', 'beneficiaries'])->lockForUpdate()->whereKey($claim->getKey())->firstOrFail();
            $actorId = $actor->getKey();
            if (! in_array($locked->status, [WelfareClaimStatus::PendingApproval, WelfareClaimStatus::UnderReview], true) || in_array($actorId, array_filter([$locked->created_by_user_id, $locked->submitted_by_user_id, $locked->reviewed_by_user_id, $locked->verified_by_user_id]), true) || $locked->approvals->contains('decided_by_user_id', $actorId)) {
                throw new InvalidWelfareOperation('Separation of duties requires a distinct approver.');
            }
            if ($locked->verification_status === WelfareVerificationStatus::Pending || $locked->verification_status === WelfareVerificationStatus::Failed || ! $this->eligibility->evaluate($locked)['eligible']) {
                throw new InvalidWelfareOperation('The claim is not eligible and verified.');
            }
            $limit = min((int) $locked->eligible_benefit_minor, (int) ($locked->product->maximum_benefit_minor ?? PHP_INT_MAX));
            if ($approvedMinor <= 0 || $approvedMinor > $limit) {
                if (! $override || trim((string) $reason) === '') {
                    throw new InvalidWelfareOperation('Approved benefit exceeds the controlled limit.');
                } $this->authorization->authorize(FoundationPermission::WelfareClaimsOverrideBenefit->value);
            }
            $secondaryRequired = $locked->product->require_secondary_approval || ($locked->product->secondary_approval_threshold_minor !== null && $approvedMinor >= $locked->product->secondary_approval_threshold_minor);
            if ($level === 'secondary' && $locked->approval_status !== WelfareApprovalStatus::PrimaryApproved) {
                throw new InvalidWelfareOperation('Primary approval is required first.');
            }
            $final = $level === 'secondary' || ! $secondaryRequired;
            $locked->approvals()->create(['organisation_id' => $locked->organisation_id, 'approval_level' => $level, 'decision' => 'approved', 'requested_at' => $locked->submitted_at, 'decided_at' => now(), 'decided_by_user_id' => $actorId, 'acting_role_id' => $this->context->role()->getKey(), 'recommended_benefit_minor' => $locked->eligible_benefit_minor, 'approved_benefit_minor' => $approvedMinor, 'reason' => $reason, 'override_type' => $override ? 'benefit_limit' : null, 'sequence_number' => $locked->approvals->count() + 1]);
            $locked->update(['approved_benefit_minor' => $approvedMinor, 'approval_status' => $final ? WelfareApprovalStatus::Approved : WelfareApprovalStatus::PrimaryApproved, 'status' => WelfareClaimStatus::PendingApproval, 'approved_at' => $final ? now() : null, 'approved_by_user_id' => $final ? $actorId : null]);
            if ($final) {
                $this->recognise->handle($actor, $locked->refresh(), $date, $idempotencyKey);
            }

            return $locked->refresh();
        }, attempts: 3);
    }
}
