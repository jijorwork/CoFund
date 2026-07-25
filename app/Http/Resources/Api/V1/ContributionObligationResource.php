<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContributionObligation */
class ContributionObligationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'sequence_number' => $this->sequence_number,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'grace_due_date' => $this->grace_due_date?->toDateString(),
            'expected_amount_minor' => $this->expected_amount_minor,
            'currency_code' => $this->currency_code,
            'status' => $this->derivedStatus()->value,
            'satisfied_amount_minor' => $this->satisfied_amount_minor,
            'outstanding_amount_minor' => $this->outstanding_amount_minor,
        ];
    }

    private function derivedStatus(): ObligationStatus
    {
        if (! in_array($this->status, [ObligationStatus::Upcoming, ObligationStatus::Due, ObligationStatus::Overdue], true)) {
            return $this->status;
        }

        $today = CarbonImmutable::now(
            $this->enrolment->contributionPlan->organisation->timezone,
        )->startOfDay();

        if ($this->grace_due_date?->isBefore($today)) {
            return ObligationStatus::Overdue;
        }

        return $this->due_date->lessThanOrEqualTo($today)
            ? ObligationStatus::Due
            : ObligationStatus::Upcoming;
    }
}
