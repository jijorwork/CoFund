<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Subscriptions\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $organisation_id
 * @property int $amount_minor
 * @property int $paid_amount_minor
 * @property string $currency_code
 * @property InvoiceStatus $status
 * @property Carbon $period_starts_on
 * @property Carbon $period_ends_on
 */
#[Fillable(['public_id', 'invoice_number', 'organisation_id', 'organisation_subscription_id', 'period_starts_on', 'period_ends_on', 'issued_on', 'due_on', 'amount_minor', 'paid_amount_minor', 'currency_code', 'status', 'paid_at'])]
class SubscriptionInvoice extends Model
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

    /** @return BelongsTo<OrganisationSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganisationSubscription::class, 'organisation_subscription_id');
    }

    /** @return HasMany<SubscriptionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    protected function casts(): array
    {
        return ['period_starts_on' => 'date', 'period_ends_on' => 'date', 'issued_on' => 'date', 'due_on' => 'date', 'amount_minor' => 'integer', 'paid_amount_minor' => 'integer', 'status' => InvoiceStatus::class, 'paid_at' => 'datetime'];
    }
}
