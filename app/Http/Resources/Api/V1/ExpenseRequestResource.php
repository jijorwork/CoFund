<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Expenses\Models\ExpenseApproval;
use App\Domain\Expenses\Models\ExpenseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseRequest */
class ExpenseRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['uuid' => $this->public_id, 'expense_number' => $this->expense_number, 'title' => $this->title, 'purpose' => $this->purpose, 'category' => $this->whenLoaded('category', fn () => ['uuid' => $this->category->public_id, 'code' => $this->category->code, 'name' => $this->category->name]), 'requester' => $this->whenLoaded('requester', fn () => ['uuid' => $this->requester->public_id, 'name' => $this->requester->name]), 'branch' => $this->whenLoaded('branch', fn () => $this->branch?->only(['public_id', 'name', 'code'])), 'requested_amount_minor' => $this->requested_amount_minor, 'approved_amount_minor' => $this->approved_amount_minor, 'paid_amount_minor' => $this->paid_amount_minor, 'outstanding_amount_minor' => $this->outstanding_amount_minor, 'currency_code' => $this->currency_code, 'needed_by_date' => $this->needed_by_date?->toDateString(), 'status' => $this->status->value, 'approval_status' => $this->approval_status->value, 'items' => ExpenseRequestItemResource::collection($this->whenLoaded('items')), 'approvals' => $this->whenLoaded('approvals', fn (): array => $this->approvals->map(fn (ExpenseApproval $approval): array => ['uuid' => $approval->public_id, 'level' => $approval->approval_level->value, 'decision' => $approval->decision->value, 'approved_amount_minor' => $approval->approved_amount_minor, 'decided_at' => $approval->decided_at?->toISOString()])->values()->all()), 'submitted_at' => $this->submitted_at?->toISOString(), 'completed_at' => $this->completed_at?->toISOString(), 'created_at' => $this->created_at?->toISOString()];
    }
}
