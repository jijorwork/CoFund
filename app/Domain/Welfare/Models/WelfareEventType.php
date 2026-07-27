<?php

namespace App\Domain\Welfare\Models;

use App\Domain\Organisations\Models\Organisation;
use Database\Factories\Domain\Welfare\Models\WelfareEventTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['public_id', 'organisation_id', 'welfare_product_id', 'code', 'name', 'description', 'event_category', 'default_benefit_minor', 'minimum_benefit_minor', 'maximum_benefit_minor', 'require_event_date', 'require_location', 'require_related_person', 'require_relationship', 'require_documentation', 'require_verification', 'claim_submission_deadline_days', 'maximum_occurrences_per_member', 'maximum_occurrences_period', 'status', 'created_by_user_id'])]
class WelfareEventType extends Model
{
    /** @use HasFactory<WelfareEventTypeFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active', 'require_event_date' => true];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<Organisation,$this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<WelfareProduct,$this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(WelfareProduct::class, 'welfare_product_id');
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['require_event_date' => 'boolean', 'require_location' => 'boolean', 'require_related_person' => 'boolean', 'require_relationship' => 'boolean', 'require_documentation' => 'boolean', 'require_verification' => 'boolean'];
    }
}
