<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Reports\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReportExport */
class ReportExportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->public_id,
            'report_key' => $this->report_key,
            'format' => $this->format->value,
            'filters' => $this->filters,
            'columns' => $this->columns,
            'sorting' => $this->sorting,
            'status' => $this->status->value,
            'file_name' => $this->file_name,
            'row_count' => $this->row_count,
            'currency_code' => $this->currency_code,
            'requested_at' => $this->requested_at->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'download_available' => $this->status->value === 'completed'
                && ($this->expires_at === null || $this->expires_at->isFuture()),
        ];
    }
}
