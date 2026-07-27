<?php

namespace App\Domain\Welfare\Models;

use Database\Factories\Domain\Welfare\Models\WelfareClaimAmendmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'organisation_id', 'welfare_claim_id', 'amendment_number', 'amendment_type', 'reason', 'original_values', 'proposed_values', 'status', 'requested_by_user_id', 'approved_by_user_id', 'approved_at'])]
class WelfareClaimAmendment extends Model
{
    /** @use HasFactory<WelfareClaimAmendmentFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['status' => 'requested'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new \LogicException('Welfare amendments cannot be deleted.'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['original_values' => 'array', 'proposed_values' => 'array', 'approved_at' => 'datetime'];
    }
}
