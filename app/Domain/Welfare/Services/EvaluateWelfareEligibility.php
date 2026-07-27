<?php

namespace App\Domain\Welfare\Services;

use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Loans\Enums\LoanPerformanceStatus;
use App\Domain\Loans\Models\Loan;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Welfare\Enums\WelfareEnrolmentStatus;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Models\WelfareClaim;
use Carbon\CarbonImmutable;

class EvaluateWelfareEligibility
{
    /** @return array<string,mixed> */
    public function evaluate(WelfareClaim $claim): array
    {
        $member = $claim->member;
        $product = $claim->product;
        $enrolment = $claim->enrolment;
        $event = $claim->event;
        $eventType = $claim->eventType;
        $reasons = [];
        $payments = ContributionPayment::query()->where('organisation_id', $claim->organisation_id)->where('member_id', $claim->member_id)->whereIn('status', [ContributionPaymentStatus::Posted->value, ContributionPaymentStatus::Allocated->value, ContributionPaymentStatus::PartiallyAllocated->value]);
        $contributionCount = (clone $payments)->count();
        $contributionTotal = (int) (clone $payments)->sum('amount_minor');
        $activeContributionEnrolment = MemberPlanEnrolment::query()->where('organisation_id', $claim->organisation_id)->where('member_id', $claim->member_id)->where('status', EnrolmentStatus::Active)->exists();
        $hasContributionArrears = ContributionObligation::query()->where('organisation_id', $claim->organisation_id)->where('member_id', $claim->member_id)->where('due_date', '<', $event->event_date)->whereIn('satisfaction_status', ['unsatisfied', 'partially_satisfied'])->exists();
        $hasOverdueLoan = Loan::query()->where('organisation_id', $claim->organisation_id)->where('member_id', $claim->member_id)->whereIn('performance_status', [LoanPerformanceStatus::Overdue, LoanPerformanceStatus::NonPerforming])->exists();
        $deadline = $eventType->claim_submission_deadline_days === null ? null : CarbonImmutable::parse($event->event_date)->addDays($eventType->claim_submission_deadline_days);
        $periodStart = CarbonImmutable::parse($event->event_date)->startOfYear();
        $priorClaims = WelfareClaim::query()->where('organisation_id', $claim->organisation_id)->where('member_id', $claim->member_id)->where('welfare_product_id', $claim->welfare_product_id)->where('id', '!=', $claim->getKey())->whereNotIn('status', ['rejected', 'cancelled', 'reversed'])->whereDate('claim_date', '>=', $periodStart)->get(['approved_benefit_minor']);
        if ($member->status !== MemberStatus::Active) {
            $reasons[] = 'member_not_active';
        }
        if ($product->status !== WelfareProductStatus::Active) {
            $reasons[] = 'product_not_active';
        }
        if ($enrolment->status !== WelfareEnrolmentStatus::Active) {
            $reasons[] = 'enrolment_not_active';
        }
        if ($member->joined_at->gt(now()->subDays($product->minimum_membership_days))) {
            $reasons[] = 'membership_duration_too_short';
        }
        if ($event->event_date->lt($enrolment->coverage_start_date)) {
            $reasons[] = 'event_before_coverage';
        }
        if ($enrolment->waiting_period_end_date !== null && $event->event_date->lt($enrolment->waiting_period_end_date)) {
            $reasons[] = 'waiting_period_not_complete';
        }
        if ($contributionCount < $product->minimum_contribution_count) {
            $reasons[] = 'insufficient_contribution_count';
        }
        if ($contributionTotal < $product->minimum_contribution_amount_minor) {
            $reasons[] = 'insufficient_contribution_value';
        }
        if ($product->require_active_contribution_enrolment && ! $activeContributionEnrolment) {
            $reasons[] = 'active_contribution_enrolment_required';
        }
        if ($product->block_member_with_arrears && $hasContributionArrears) {
            $reasons[] = 'contribution_arrears_exist';
        }
        if ($product->block_member_with_overdue_loan && $hasOverdueLoan) {
            $reasons[] = 'overdue_loan_exists';
        }
        if ($deadline !== null && $claim->claim_date->gt($deadline)) {
            $reasons[] = 'claim_submission_deadline_passed';
        }
        if ($product->maximum_claims_per_period !== null && $priorClaims->count() >= $product->maximum_claims_per_period) {
            $reasons[] = 'claim_frequency_limit_reached';
        }
        $periodBenefits = (int) $priorClaims->sum('approved_benefit_minor');
        $maximum = (int) ($product->maximum_benefit_minor ?? PHP_INT_MAX);
        if ($product->maximum_total_benefit_per_period_minor !== null) {
            $maximum = min($maximum, max(0, $product->maximum_total_benefit_per_period_minor - $periodBenefits));
        }

        return ['eligible' => $reasons === [], 'eligibility_status' => $reasons === [] ? 'eligible' : 'ineligible', 'blocking_reasons' => $reasons, 'warnings' => [], 'coverage_summary' => ['coverage_start_date' => $enrolment->coverage_start_date->toDateString()], 'waiting_period_summary' => ['ends_on' => $enrolment->waiting_period_end_date?->toDateString()], 'contribution_summary' => ['count' => $contributionCount, 'total_minor' => $contributionTotal, 'active_enrolment' => $activeContributionEnrolment, 'has_arrears' => $hasContributionArrears], 'prior_claim_summary' => ['count' => $priorClaims->count(), 'approved_minor' => $periodBenefits], 'maximum_eligible_benefit_minor' => $maximum, 'required_documents' => $product->required_document_types ?? [], 'required_verifications' => array_values(array_filter([$product->require_verification ? 'claim' : null, $eventType->require_verification ? 'event' : null])), 'required_approval_level' => $product->require_secondary_approval ? 'secondary' : 'primary'];
    }
}
