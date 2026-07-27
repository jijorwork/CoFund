<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareClaimStatus;
use App\Domain\Welfare\Enums\WelfareEligibilityStatus;
use App\Domain\Welfare\Enums\WelfareVerificationStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareClaim;
use App\Domain\Welfare\Services\CalculateWelfareBenefit;
use App\Domain\Welfare\Services\EvaluateWelfareEligibility;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitWelfareClaim
{
    public function __construct(private RoleAuthorization $authorization, private EvaluateWelfareEligibility $eligibility, private CalculateWelfareBenefit $benefits) {}

    public function handle(User $actor, WelfareClaim $claim, bool $override = false, ?string $reason = null): WelfareClaim
    {
        $this->authorization->authorize(FoundationPermission::WelfareClaimsSubmit->value);

        return DB::transaction(function () use ($actor, $claim, $override, $reason): WelfareClaim {
            $locked = WelfareClaim::query()->with(['member', 'product', 'enrolment', 'event', 'eventType', 'beneficiaries', 'documents'])->lockForUpdate()->whereKey($claim->getKey())->firstOrFail();
            if ($locked->status !== WelfareClaimStatus::Draft) {
                throw new InvalidWelfareOperation('Only draft claims may be submitted.');
            }
            $eligibility = $this->eligibility->evaluate($locked);
            if (! $eligibility['eligible']) {
                if (! $override || trim((string) $reason) === '') {
                    throw new InvalidWelfareOperation('The welfare claim is not eligible.');
                } $this->authorization->authorize(FoundationPermission::WelfareClaimsOverrideEligibility->value);
                $eligibility['override_reason'] = $reason;
                $eligibility['override_by_user_id'] = $actor->getKey();
            }
            $requiredDocuments = $locked->product->required_document_types ?? [];
            $present = $locked->documents->pluck('document_type');
            if (collect($requiredDocuments)->contains(fn ($type): bool => ! $present->contains($type))) {
                throw new InvalidWelfareOperation('Required welfare documents are missing.');
            }
            if ($locked->product->require_beneficiary && $locked->beneficiaries->where('status', 'active')->isEmpty()) {
                throw new InvalidWelfareOperation('A beneficiary is required.');
            }
            $benefit = $this->benefits->calculate($locked);
            $eligibleAmount = min($benefit['eligible_benefit_minor'], $eligibility['maximum_eligible_benefit_minor']);
            $locked->terms()->create(['organisation_id' => $locked->organisation_id, 'currency_code' => $locked->currency_code, 'benefit_calculation_type' => $locked->product->benefit_calculation_type, 'benefit_rate_basis_points' => $benefit['rate_basis_points'], 'benefit_base_minor' => $benefit['base_amount_minor'], 'minimum_benefit_minor' => $locked->product->minimum_benefit_minor, 'maximum_benefit_minor' => $locked->product->maximum_benefit_minor, 'requested_benefit_minor' => $locked->requested_benefit_minor, 'calculated_benefit_minor' => $eligibleAmount, 'waiting_period_end_date' => $locked->enrolment->waiting_period_end_date, 'claim_deadline_date' => $locked->eventType->claim_submission_deadline_days === null ? null : $locked->event->event_date->addDays($locked->eventType->claim_submission_deadline_days), 'required_documents' => $requiredDocuments, 'required_approval_level' => $locked->product->require_secondary_approval ? 'secondary' : 'primary', 'eligibility_snapshot' => $eligibility]);
            $needsVerification = $locked->product->require_verification || $locked->eventType->require_verification;
            $locked->update(['status' => $needsVerification ? WelfareClaimStatus::PendingVerification : WelfareClaimStatus::UnderReview, 'eligibility_status' => $override ? WelfareEligibilityStatus::Overridden : WelfareEligibilityStatus::Eligible, 'verification_status' => $needsVerification ? WelfareVerificationStatus::Pending : WelfareVerificationStatus::NotRequired, 'eligible_benefit_minor' => $eligibleAmount, 'eligibility_snapshot' => $eligibility, 'benefit_snapshot' => $benefit, 'submitted_at' => now(), 'submission_date' => now()->toDateString(), 'submitted_by_user_id' => $actor->getKey()]);

            return $locked->refresh();
        });
    }
}
