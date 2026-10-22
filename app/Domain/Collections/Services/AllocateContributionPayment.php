<?php

namespace App\Domain\Collections\Services;

use App\Domain\Collections\Data\ContributionAllocationData;
use App\Domain\Collections\Data\ContributionPaymentData;
use App\Domain\Collections\Data\PaymentAllocationData;
use App\Domain\Collections\Data\PaymentAllocationResult;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Collections\Exceptions\InvalidContributionPayment;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AllocateContributionPayment
{
    public function allocate(ContributionPaymentData $data): PaymentAllocationResult
    {
        return $this->calculate(
            ContributionAllocationData::fromPayment($data),
            lockForUpdate: true,
        );
    }

    public function preview(ContributionAllocationData $data): PaymentAllocationResult
    {
        return $this->calculate($data, lockForUpdate: false);
    }

    private function calculate(
        ContributionAllocationData $data,
        bool $lockForUpdate,
    ): PaymentAllocationResult {
        if ($data->allocationStrategy === PaymentAllocationStrategy::LeaveUnallocated) {
            return new PaymentAllocationResult([], 0, $data->amountMinor);
        }

        $enrolment = $data->enrolment;
        $plan = $data->plan;

        if ($enrolment === null || $plan === null) {
            throw new InvalidContributionPayment('An active enrolment is required for allocation.');
        }

        $obligations = ContributionObligation::query()
            ->whereBelongsTo($enrolment, 'enrolment')
            ->whereNotIn('schedule_status', [ObligationStatus::Cancelled, ObligationStatus::Waived])
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->get();

        if ($data->allocationStrategy === PaymentAllocationStrategy::SelectedObligations) {
            return $this->selected($data, $obligations);
        }

        $today = CarbonImmutable::now($enrolment->organisation->timezone)->startOfDay();
        $ordered = $data->allocationStrategy === PaymentAllocationStrategy::CurrentPeriodFirst
            ? $obligations->sortBy(fn (ContributionObligation $obligation): array => [
                $obligation->due_date->isSameDay($today) ? 0 : 1,
                $obligation->due_date->isBefore($today) ? 0 : 1,
                $obligation->due_date->timestamp,
                $obligation->sequence_number,
            ])->values()
            : $obligations->sortBy(fn (ContributionObligation $obligation): array => [
                $obligation->due_date->timestamp,
                $obligation->sequence_number,
            ])->values();

        $remaining = $data->amountMinor;
        $allocations = [];

        foreach ($ordered as $obligation) {
            if ($remaining === 0) {
                break;
            }

            if ($obligation->due_date->isAfter($today) && ! $plan->allow_advance_payments) {
                continue;
            }

            $outstanding = (int) $obligation->outstanding_amount_minor;

            if ($outstanding <= 0) {
                continue;
            }

            if ($remaining < $outstanding && ! $plan->allow_partial_payments) {
                throw new InvalidContributionPayment('Partial payments are disabled for this contribution plan.');
            }

            $amount = min($remaining, $outstanding);
            $allocations[] = new PaymentAllocationData($obligation, $amount);
            $remaining -= $amount;
        }

        if ($remaining > 0 && ! $plan->allow_overpayments) {
            throw new InvalidContributionPayment('The payment exceeds eligible outstanding obligations.');
        }

        return new PaymentAllocationResult($allocations, $data->amountMinor - $remaining, $remaining);
    }

    /**
     * @param  Collection<int, ContributionObligation>  $obligations
     */
    private function selected(ContributionAllocationData $data, Collection $obligations): PaymentAllocationResult
    {
        if ($data->selectedAllocations === []) {
            throw new InvalidContributionPayment('Selected obligations are required.');
        }

        $today = CarbonImmutable::now($data->enrolment?->organisation->timezone)->startOfDay();
        $seen = [];
        $total = 0;

        foreach ($data->selectedAllocations as $allocation) {
            $obligation = $obligations->firstWhere('id', $allocation->obligation->getKey());

            if (
                $obligation === null
                || isset($seen[$obligation->getKey()])
                || $allocation->amountMinor <= 0
                || $allocation->amountMinor > $obligation->outstanding_amount_minor
                || ($obligation->due_date->isAfter($today) && ! $data->plan?->allow_advance_payments)
                || ($allocation->amountMinor < $obligation->outstanding_amount_minor
                    && ! $data->plan?->allow_partial_payments)
            ) {
                throw new InvalidContributionPayment('A selected obligation allocation is invalid.');
            }

            $seen[$obligation->getKey()] = true;
            $total += $allocation->amountMinor;
        }

        if ($total > $data->amountMinor) {
            throw new InvalidContributionPayment('Allocations exceed the payment amount.');
        }

        $remainder = $data->amountMinor - $total;

        if ($remainder > 0 && ! $data->plan?->allow_overpayments) {
            throw new InvalidContributionPayment('The unallocated remainder is not allowed by the plan.');
        }

        return new PaymentAllocationResult($data->selectedAllocations, $total, $remainder);
    }
}
