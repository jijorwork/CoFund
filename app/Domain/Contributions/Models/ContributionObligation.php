<?php

namespace App\Domain\Contributions\Models;

use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Contributions\Models\ContributionObligationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ObligationStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $due_date
 * @property Carbon|null $grace_due_date
 */
#[Fillable([
    'public_id', 'organisation_id', 'contribution_plan_id',
    'member_plan_enrolment_id', 'member_id', 'sequence_number', 'period_start',
    'period_end', 'due_date', 'expected_amount_minor', 'currency_code', 'status',
    'grace_due_date', 'satisfied_amount_minor', 'outstanding_amount_minor',
    'satisfied_at', 'generated_at',
])]
class ContributionObligation extends Model
{
    /** @use HasFactory<ContributionObligationFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'upcoming',
        'satisfied_amount_minor' => 0,
        'outstanding_amount_minor' => 0,
    ];

    protected static function newFactory(): ContributionObligationFactory
    {
        return ContributionObligationFactory::new();
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<ContributionPlan, $this> */
    public function contributionPlan(): BelongsTo
    {
        return $this->belongsTo(ContributionPlan::class);
    }

    /** @return BelongsTo<MemberPlanEnrolment, $this> */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(MemberPlanEnrolment::class, 'member_plan_enrolment_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ObligationStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'grace_due_date' => 'date',
            'satisfied_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }
}
