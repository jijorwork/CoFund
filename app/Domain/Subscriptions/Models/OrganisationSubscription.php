<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $organisation_id
 * @property SubscriptionStatus $status
 * @property BillingInterval $billing_interval
 * @property Carbon $current_period_ends_on
 */
#[Fillable(['public_id', 'organisation_id', 'subscription_plan_id', 'billing_interval', 'status', 'starts_on', 'current_period_starts_on', 'current_period_ends_on', 'grace_ends_on', 'cancelled_on', 'active_scope_key', 'created_by_user_id', 'metadata'])]
class OrganisationSubscription extends Model
{
    use HasUuids;

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

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<SubscriptionInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class)->latest();
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess()
            && $this->current_period_ends_on->greaterThanOrEqualTo(today());
    }

    protected function casts(): array
    {
        return ['billing_interval' => BillingInterval::class, 'status' => SubscriptionStatus::class, 'starts_on' => 'date', 'current_period_starts_on' => 'date', 'current_period_ends_on' => 'date', 'grace_ends_on' => 'date', 'cancelled_on' => 'date', 'metadata' => 'array'];
    }
}
