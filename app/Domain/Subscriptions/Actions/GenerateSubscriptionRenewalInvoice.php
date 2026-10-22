<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\InvoiceStatus;
use App\Domain\Subscriptions\Exceptions\InvalidSubscriptionOperation;
use App\Domain\Subscriptions\Models\OrganisationSubscription;
use App\Domain\Subscriptions\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateSubscriptionRenewalInvoice
{
    public function handle(User $actor, OrganisationSubscription $subscription): SubscriptionInvoice
    {
        if (! $actor->hasRole(SystemRole::PlatformAdmin->value, 'web')) {
            throw new InvalidSubscriptionOperation('Only a platform administrator may generate renewal invoices.');
        }

        return DB::transaction(function () use ($subscription): SubscriptionInvoice {
            $lockedSubscription = OrganisationSubscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->sole();
            if ($lockedSubscription->invoices()->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Overdue,
            ])->exists()) {
                throw new InvalidSubscriptionOperation('An unpaid subscription invoice already exists.');
            }

            $periodStart = $lockedSubscription->current_period_ends_on->copy()->addDay();
            $periodEnd = $lockedSubscription->billing_interval === BillingInterval::Monthly
                ? $periodStart->copy()->addMonthNoOverflow()->subDay()
                : $periodStart->copy()->addYear()->subDay();
            $plan = $lockedSubscription->plan()->firstOrFail();

            return SubscriptionInvoice::query()->create([
                'invoice_number' => 'SUB-'.$periodStart->format('Ymd').'-'.Str::upper(Str::random(8)),
                'organisation_id' => $lockedSubscription->organisation_id,
                'organisation_subscription_id' => $lockedSubscription->getKey(),
                'period_starts_on' => $periodStart,
                'period_ends_on' => $periodEnd,
                'issued_on' => today(),
                'due_on' => $periodStart,
                'amount_minor' => $plan->priceFor($lockedSubscription->billing_interval),
                'paid_amount_minor' => 0,
                'currency_code' => $plan->currency_code,
                'status' => InvoiceStatus::Issued,
            ]);
        });
    }
}
