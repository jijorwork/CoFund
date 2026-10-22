<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Subscriptions\Enums\InvoiceStatus;
use App\Domain\Subscriptions\Enums\ManualPaymentMethod;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Exceptions\InvalidSubscriptionOperation;
use App\Domain\Subscriptions\Models\SubscriptionInvoice;
use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordManualSubscriptionPayment
{
    public function handle(
        User $actor,
        SubscriptionInvoice $invoice,
        string $reference,
        ManualPaymentMethod $method,
        int $amountMinor,
        CarbonInterface $paidOn,
        ?string $notes = null,
    ): SubscriptionPayment {
        if (! $actor->hasRole(SystemRole::PlatformAdmin->value, 'web')) {
            throw new InvalidSubscriptionOperation('Only a platform administrator may record subscription payments.');
        }
        if ($amountMinor <= 0 || $invoice->status === InvoiceStatus::Void) {
            throw new InvalidSubscriptionOperation('The payment is invalid.');
        }

        return DB::transaction(function () use ($actor, $invoice, $reference, $method, $amountMinor, $paidOn, $notes): SubscriptionPayment {
            $existing = SubscriptionPayment::query()->where('payment_reference', $reference)->first();
            if ($existing !== null) {
                if ($existing->subscription_invoice_id !== $invoice->getKey() || $existing->amount_minor !== $amountMinor) {
                    throw new InvalidSubscriptionOperation('The payment reference was reused with different details.');
                }

                return $existing;
            }
            $lockedInvoice = SubscriptionInvoice::query()->whereKey($invoice->getKey())->lockForUpdate()->sole();
            $remaining = $lockedInvoice->amount_minor - $lockedInvoice->paid_amount_minor;
            if ($amountMinor > $remaining) {
                throw new InvalidSubscriptionOperation('The payment exceeds the invoice balance.');
            }
            $payment = SubscriptionPayment::query()->create([
                'organisation_id' => $lockedInvoice->organisation_id,
                'subscription_invoice_id' => $lockedInvoice->getKey(),
                'payment_reference' => $reference,
                'payment_method' => $method,
                'amount_minor' => $amountMinor,
                'currency_code' => $lockedInvoice->currency_code,
                'paid_on' => $paidOn,
                'recorded_by_user_id' => $actor->getKey(),
                'notes' => $notes,
                'metadata' => ['source' => 'platform_filament'],
            ]);
            $paidAmount = $lockedInvoice->paid_amount_minor + $amountMinor;
            $isPaid = $paidAmount === $lockedInvoice->amount_minor;
            $lockedInvoice->update([
                'paid_amount_minor' => $paidAmount,
                'status' => $isPaid ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid,
                'paid_at' => $isPaid ? now() : null,
            ]);
            if ($isPaid) {
                $subscription = $lockedInvoice->subscription()->firstOrFail();
                $graceDays = $subscription->plan()->firstOrFail()->grace_days;
                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'current_period_starts_on' => $lockedInvoice->period_starts_on,
                    'current_period_ends_on' => $lockedInvoice->period_ends_on,
                    'grace_ends_on' => $graceDays > 0
                        ? $lockedInvoice->period_ends_on->copy()->addDays($graceDays)
                        : null,
                ]);
            }

            return $payment;
        });
    }
}
