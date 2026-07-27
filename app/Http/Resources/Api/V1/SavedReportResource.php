<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reports\Models\SavedReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SavedReport */
class SavedReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'report_key' => $this->report_key,
            'filters' => $this->filters,
            'grouping' => $this->grouping,
            'sorting' => $this->sorting,
            'columns' => $this->columns,
            'visibility' => $this->visibility->value,
            'is_favourite' => $this->is_favourite,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
