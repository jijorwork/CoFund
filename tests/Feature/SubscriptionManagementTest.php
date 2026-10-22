<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Subscriptions\Actions\GenerateSubscriptionRenewalInvoice;
use App\Domain\Subscriptions\Actions\RecordManualSubscriptionPayment;
use App\Domain\Subscriptions\Actions\StartOrganisationSubscription;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\InvoiceStatus;
use App\Domain\Subscriptions\Enums\ManualPaymentMethod;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Exceptions\InvalidSubscriptionOperation;
use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Domain\Subscriptions\Services\SubscriptionAccess;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;

beforeEach(function () {
    app(SystemAuthorizationSeeder::class)->run();
});

test('manual monthly subscription payment activates access and is idempotent', function () {
    $actor = User::factory()->create();
    $actor->assignRole(SystemRole::PlatformAdmin->value);
    $organisation = Organisation::factory()->create();
    $plan = SubscriptionPlan::query()->create([
        'code' => 'STANDARD',
        'name' => 'Standard',
        'monthly_price_minor' => 25000,
        'yearly_price_minor' => 250000,
        'currency_code' => 'GHS',
        'grace_days' => 7,
        'is_active' => true,
    ]);

    $invoice = app(StartOrganisationSubscription::class)->handle(
        $actor,
        $organisation,
        $plan,
        BillingInterval::Monthly,
        CarbonImmutable::parse('2026-08-01'),
    );

    expect($invoice->amount_minor)->toBe(25000)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->subscription->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and(app(SubscriptionAccess::class)->allows($organisation))->toBeFalse();

    $payments = app(RecordManualSubscriptionPayment::class);
    $first = $payments->handle(
        $actor,
        $invoice,
        'BANK-1001',
        ManualPaymentMethod::BankTransfer,
        10000,
        CarbonImmutable::parse('2026-08-01'),
    );
    $second = $payments->handle(
        $actor,
        $invoice->refresh(),
        'BANK-1002',
        ManualPaymentMethod::BankTransfer,
        15000,
        CarbonImmutable::parse('2026-08-02'),
    );
    $duplicate = $payments->handle(
        $actor,
        $invoice->refresh(),
        'BANK-1002',
        ManualPaymentMethod::BankTransfer,
        15000,
        CarbonImmutable::parse('2026-08-02'),
    );

    expect($first->amount_minor)->toBe(10000)
        ->and($second->is($duplicate))->toBeTrue()
        ->and(SubscriptionPayment::query()->count())->toBe(2)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_amount_minor)->toBe(25000)
        ->and($invoice->subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(app(SubscriptionAccess::class)->allows($organisation))->toBeTrue();

    $renewal = app(GenerateSubscriptionRenewalInvoice::class)->handle(
        $actor,
        $invoice->subscription,
    );
    expect($renewal->period_starts_on->toDateString())->toBe('2026-09-01')
        ->and($renewal->period_ends_on->toDateString())->toBe('2026-09-30');

    $payments->handle(
        $actor,
        $renewal,
        'BANK-1003',
        ManualPaymentMethod::BankTransfer,
        25000,
        CarbonImmutable::parse('2026-08-25'),
    );

    expect($renewal->subscription->refresh()->current_period_ends_on->toDateString())
        ->toBe('2026-09-30');
});

test('manual billing rejects duplicate subscriptions overpayments and unauthorised actors', function () {
    $actor = User::factory()->create();
    $actor->assignRole(SystemRole::PlatformAdmin->value);
    $ordinaryUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $plan = SubscriptionPlan::query()->create([
        'code' => 'ANNUAL',
        'name' => 'Annual',
        'monthly_price_minor' => 10000,
        'yearly_price_minor' => 100000,
        'currency_code' => 'GHS',
        'grace_days' => 0,
        'is_active' => true,
    ]);
    $start = app(StartOrganisationSubscription::class);
    $invoice = $start->handle($actor, $organisation, $plan, BillingInterval::Yearly, today());

    expect(fn () => $start->handle($actor, $organisation, $plan, BillingInterval::Monthly, today()))
        ->toThrow(InvalidSubscriptionOperation::class)
        ->and(fn () => app(RecordManualSubscriptionPayment::class)->handle(
            $ordinaryUser,
            $invoice,
            'CASH-1',
            ManualPaymentMethod::Cash,
            100000,
            today(),
        ))->toThrow(InvalidSubscriptionOperation::class)
        ->and(fn () => app(RecordManualSubscriptionPayment::class)->handle(
            $actor,
            $invoice,
            'CASH-2',
            ManualPaymentMethod::Cash,
            100001,
            today(),
        ))->toThrow(InvalidSubscriptionOperation::class);
});
