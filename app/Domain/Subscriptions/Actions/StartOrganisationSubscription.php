<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Organisations\Enums\OrganisationStatus;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\InvoiceStatus;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Exceptions\InvalidSubscriptionOperation;
use App\Domain\Subscriptions\Models\OrganisationSubscription;
use App\Domain\Subscriptions\Models\SubscriptionInvoice;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartOrganisationSubscription
{
    public function handle(
        User $actor,
        Organisation $organisation,
        SubscriptionPlan $plan,
        BillingInterval $interval,
        CarbonInterface $startsOn,
    ): SubscriptionInvoice {
        if (! $actor->hasRole(SystemRole::PlatformAdmin->value, 'web')) {
            throw new InvalidSubscriptionOperation('Only a platform administrator may start subscriptions.');
        }
        if ($organisation->status !== OrganisationStatus::Active || ! $plan->is_active) {
            throw new InvalidSubscriptionOperation('The organisation and plan must be active.');
        }

        return DB::transaction(function () use ($actor, $organisation, $plan, $interval, $startsOn): SubscriptionInvoice {
            if (OrganisationSubscription::query()->where('active_scope_key', (string) $organisation->getKey())->lockForUpdate()->exists()) {
                throw new InvalidSubscriptionOperation('The organisation already has a current subscription.');
            }
            $periodStart = $startsOn->copy()->startOfDay();
            $periodEnd = $interval === BillingInterval::Monthly
                ? $periodStart->copy()->addMonthNoOverflow()->subDay()
                : $periodStart->copy()->addYear()->subDay();
            $subscription = OrganisationSubscription::query()->create([
                'organisation_id' => $organisation->getKey(),
                'subscription_plan_id' => $plan->getKey(),
                'billing_interval' => $interval,
                'status' => SubscriptionStatus::PendingPayment,
                'starts_on' => $periodStart,
                'current_period_starts_on' => $periodStart,
                'current_period_ends_on' => $periodEnd,
                'grace_ends_on' => $plan->grace_days > 0 ? $periodEnd->copy()->addDays($plan->grace_days) : null,
                'active_scope_key' => (string) $organisation->getKey(),
                'created_by_user_id' => $actor->getKey(),
                'metadata' => ['source' => 'platform_filament'],
            ]);

            return SubscriptionInvoice::query()->create([
                'invoice_number' => 'SUB-'.$periodStart->format('Ymd').'-'.Str::upper(Str::random(8)),
                'organisation_id' => $organisation->getKey(),
                'organisation_subscription_id' => $subscription->getKey(),
                'period_starts_on' => $periodStart,
                'period_ends_on' => $periodEnd,
                'issued_on' => today(),
                'due_on' => $periodStart,
                'amount_minor' => $plan->priceFor($interval),
                'paid_amount_minor' => 0,
                'currency_code' => $plan->currency_code,
                'status' => InvoiceStatus::Issued,
            ]);
        });
    }
}
