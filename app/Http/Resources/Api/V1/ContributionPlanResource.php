<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Contributions\Models\ContributionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContributionPlan */
class ContributionPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'plan_type' => $this->plan_type->value,
            'frequency' => $this->frequency->value,
            'amount_type' => $this->amount_type->value,
            'fixed_amount_minor' => $this->fixed_amount_minor,
            'fixed_amount' => $this->money($this->fixed_amount_minor),
            'minimum_amount_minor' => $this->minimum_amount_minor,
            'minimum_amount' => $this->money($this->minimum_amount_minor),
            'maximum_amount_minor' => $this->maximum_amount_minor,
            'maximum_amount' => $this->money($this->maximum_amount_minor),
            'currency_code' => $this->currency_code,
            'branch' => $this->branch === null ? null : [
                'uuid' => $this->branch->public_id,
                'name' => $this->branch->name,
            ],
            'group' => $this->group === null ? null : [
                'uuid' => $this->group->public_id,
                'name' => $this->group->name,
            ],
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'collection_day_of_week' => $this->collection_day_of_week,
            'collection_day_of_month' => $this->collection_day_of_month,
            'grace_period_days' => $this->grace_period_days,
            'allow_partial_payments' => $this->allow_partial_payments,
            'allow_advance_payments' => $this->allow_advance_payments,
            'allow_overpayments' => $this->allow_overpayments,
            'requires_approval_to_join' => $this->requires_approval_to_join,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function money(?int $minor): ?string
    {
        if ($minor === null) {
            return null;
        }

        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
