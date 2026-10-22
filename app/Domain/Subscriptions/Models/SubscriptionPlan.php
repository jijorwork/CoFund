<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Subscriptions\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $monthly_price_minor
 * @property int $yearly_price_minor
 * @property string $currency_code
 * @property int $grace_days
 * @property bool $is_active
 */
#[Fillable(['public_id', 'code', 'name', 'description', 'monthly_price_minor', 'yearly_price_minor', 'currency_code', 'grace_days', 'limits', 'is_active'])]
class SubscriptionPlan extends Model
{
    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function priceFor(BillingInterval $interval): int
    {
        return $interval === BillingInterval::Monthly ? $this->monthly_price_minor : $this->yearly_price_minor;
    }

    /** @return HasMany<OrganisationSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganisationSubscription::class);
    }

    protected function casts(): array
    {
        return ['monthly_price_minor' => 'integer', 'yearly_price_minor' => 'integer', 'grace_days' => 'integer', 'limits' => 'array', 'is_active' => 'boolean'];
    }
}
