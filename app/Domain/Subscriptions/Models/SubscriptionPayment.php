<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Subscriptions\Enums\ManualPaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $subscription_invoice_id
 * @property int $amount_minor
 */
#[Fillable(['public_id', 'organisation_id', 'subscription_invoice_id', 'payment_reference', 'payment_method', 'amount_minor', 'currency_code', 'paid_on', 'recorded_by_user_id', 'notes', 'metadata'])]
class SubscriptionPayment extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

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

    /** @return BelongsTo<SubscriptionInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new LogicException('Subscription payments are immutable.'));
        static::deleting(fn (): bool => throw new LogicException('Subscription payments are immutable.'));
    }

    protected function casts(): array
    {
        return ['payment_method' => ManualPaymentMethod::class, 'amount_minor' => 'integer', 'paid_on' => 'date', 'metadata' => 'array', 'created_at' => 'datetime'];
    }
}
