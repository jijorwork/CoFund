<?php

namespace App\Domain\Loans\Models;

use App\Domain\Loans\Enums\GuarantorStatus;
use App\Domain\Membership\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property GuarantorStatus $status
 * @property Carbon $invited_at
 * @property Carbon|null $accepted_at
 */
#[Fillable(['public_id', 'organisation_id', 'loan_application_id', 'guarantor_member_id', 'guaranteed_amount_minor', 'guarantee_percentage_basis_points', 'status', 'invited_at', 'accepted_at', 'declined_at', 'revoked_at', 'released_at', 'consent_document_path', 'notes', 'active_guarantor_key'])]
class LoanGuarantor extends Model
{
    use HasUuids;

    protected $attributes = ['status' => 'invited'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<LoanApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guarantor_member_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => GuarantorStatus::class, 'invited_at' => 'datetime', 'accepted_at' => 'datetime', 'declined_at' => 'datetime', 'revoked_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
