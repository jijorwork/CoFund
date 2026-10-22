<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Organisations\Models\Organisation;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\OrganisationSubscription;
use App\Domain\Subscriptions\Models\SubscriptionPlan;

class SubscriptionAccess
{
    public function allows(Organisation $organisation): bool
    {
        if (! SubscriptionPlan::query()->where('is_active', true)->exists()) {
            return true;
        }

        return OrganisationSubscription::query()
            ->whereBelongsTo($organisation)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('status', SubscriptionStatus::Active)
                    ->whereDate('current_period_ends_on', '>=', today()))
                ->orWhere(fn ($query) => $query
                    ->where('status', SubscriptionStatus::GracePeriod)
                    ->whereDate('grace_ends_on', '>=', today())))
            ->exists();
    }
}
