<?php

use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Collections\Actions\RecordContributionPayment;
use App\Domain\Collections\Actions\ReverseContributionPayment;
use App\Domain\Collections\Data\ContributionPaymentData;
use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\ContributionPaymentStatus;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Collections\Enums\ReceiptStatus;
use App\Domain\Collections\Exceptions\InvalidContributionPayment;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Contributions\Enums\ObligationSatisfactionStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Notifications\Actions\CreateNotificationDispatch;
use App\Domain\Notifications\Models\NotificationDispatch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Actions\CreateOrganisationWallet;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Support\Facades\Queue;

function collectionContext(User $actor, Organisation $organisation): void
{
    $assignment = OrganisationUserRole::factory()
        ->for($actor)
        ->for($organisation)
        ->state(['role_id' => Role::findByName(SystemRole::Treasurer->value)->getKey()])
        ->create();
    app(TenantContext::class)->setOrganisation($organisation);
    app(TenantContext::class)->setBranch(null);
    app(TenantContext::class)->setRoleAssignment($assignment);
}

function collectionFixture(): array
{
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'UNITY']);
    collectionContext($actor, $organisation);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $actor);
    $source = app(CreateOrganisationWallet::class)->handle(
        $actor,
        WalletType::OfficeCash,
        'Office Cash',
        'OFFICE-CASH',
    );
    $member = Member::factory()->active()->for($organisation)->create();
    $plan = ContributionPlan::factory()->active()->for($organisation)->create([
        'allow_partial_payments' => true,
        'allow_advance_payments' => true,
        'allow_overpayments' => true,
    ]);
    $enrolment = MemberPlanEnrolment::factory()->active()->for($plan, 'contributionPlan')->for($member)->create();
    $obligation = ContributionObligation::factory()->due()->for($enrolment, 'enrolment')->create([
        'organisation_id' => $organisation->id,
        'contribution_plan_id' => $plan->id,
        'member_id' => $member->id,
        'expected_amount_minor' => 10000,
        'outstanding_amount_minor' => 10000,
    ]);
    $destination = app(CreateMemberWallet::class)->handle(
        $actor,
        $member,
        WalletType::MemberSavings,
        'Savings',
        'SAV-'.$member->membership_number,
    );

    return compact('actor', 'organisation', 'source', 'member', 'plan', 'enrolment', 'obligation', 'destination');
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('office cash contribution posts once allocates deterministically and issues a receipt', function () {
    $fixture = collectionFixture();
    $data = new ContributionPaymentData(
        member: $fixture['member'],
        enrolment: $fixture['enrolment'],
        plan: $fixture['plan'],
        amountMinor: 15000,
        currencyCode: 'GHS',
        paymentMethod: ContributionPaymentMethod::Cash,
        paymentDate: CarbonImmutable::today(),
        sourceWallet: $fixture['source'],
        destinationWallet: $fixture['destination'],
        allocationStrategy: PaymentAllocationStrategy::OldestDueFirst,
        idempotencyKey: 'payment-one',
    );
    $payment = app(RecordContributionPayment::class)->handle($fixture['actor'], $data);
    $retry = app(RecordContributionPayment::class)->handle($fixture['actor'], $data);

    expect($retry->is($payment))->toBeTrue()
        ->and($payment->payment_number)->toBe('UNITY-PAY-'.now()->year.'-000001')
        ->and($payment->allocated_amount_minor)->toBe(10000)
        ->and($payment->unallocated_amount_minor)->toBe(5000)
        ->and($payment->financialTransaction->entries)->toHaveCount(2)
        ->and($payment->receipt)->toBeInstanceOf(Receipt::class)
        ->and(ContributionPayment::query()->count())->toBe(1)
        ->and(ContributionPaymentAllocation::query()->count())->toBe(1)
        ->and($fixture['obligation']->refresh()->satisfaction_status)->toBe(ObligationSatisfactionStatus::Satisfied)
        ->and(app(CalculateWalletBalance::class)->calculate($fixture['source'])->displayBalanceMinor)->toBe(15000)
        ->and(app(CalculateWalletBalance::class)->calculate($fixture['destination'])->displayBalanceMinor)->toBe(15000);
});

test('linked member receives one contribution confirmation despite an idempotent payment retry', function () {
    Queue::fake();
    $fixture = collectionFixture();
    $recipient = User::factory()->create();
    $fixture['member']->update(['user_id' => $recipient->getKey()]);
    $data = new ContributionPaymentData(
        member: $fixture['member'],
        enrolment: $fixture['enrolment'],
        plan: $fixture['plan'],
        amountMinor: 10000,
        currencyCode: 'GHS',
        paymentMethod: ContributionPaymentMethod::Cash,
        paymentDate: CarbonImmutable::today(),
        sourceWallet: $fixture['source'],
        destinationWallet: $fixture['destination'],
        allocationStrategy: PaymentAllocationStrategy::OldestDueFirst,
        idempotencyKey: 'payment-notification-one',
    );

    $payment = app(RecordContributionPayment::class)->handle($fixture['actor'], $data);
    app(RecordContributionPayment::class)->handle($fixture['actor'], $data);

    $dispatch = NotificationDispatch::query()->sole();
    expect($dispatch->event_key)->toBe('contribution.received')
        ->and($dispatch->source_uuid)->toBe($payment->public_id)
        ->and($dispatch->user_id)->toBe($recipient->getKey())
        ->and($dispatch->body)->toContain($payment->receipt->receipt_number)
        ->and(NotificationDispatch::query()->count())->toBe(1);
});

test('a failed contribution notification rolls back the payment and receipt', function () {
    Queue::fake();
    $fixture = collectionFixture();
    $recipient = User::factory()->create();
    $fixture['member']->update(['user_id' => $recipient->getKey()]);
    $notifications = Mockery::mock(CreateNotificationDispatch::class);
    $notifications->shouldReceive('handle')->once()->andThrow(new RuntimeException('Dispatch unavailable'));
    app()->instance(CreateNotificationDispatch::class, $notifications);
    $data = new ContributionPaymentData(
        member: $fixture['member'],
        enrolment: $fixture['enrolment'],
        plan: $fixture['plan'],
        amountMinor: 10000,
        currencyCode: 'GHS',
        paymentMethod: ContributionPaymentMethod::Cash,
        paymentDate: CarbonImmutable::today(),
        sourceWallet: $fixture['source'],
        destinationWallet: $fixture['destination'],
        allocationStrategy: PaymentAllocationStrategy::OldestDueFirst,
        idempotencyKey: 'payment-notification-failure',
    );

    expect(fn () => app(RecordContributionPayment::class)->handle($fixture['actor'], $data))
        ->toThrow(RuntimeException::class);
    expect(ContributionPayment::query()->count())->toBe(0)
        ->and(Receipt::query()->count())->toBe(0)
        ->and(NotificationDispatch::query()->count())->toBe(0)
        ->and($fixture['obligation']->refresh()->outstanding_amount_minor)->toBe(10000);
});

test('payment reversal restores obligation and wallet balances while preserving receipts', function () {
    $fixture = collectionFixture();
    $payment = app(RecordContributionPayment::class)->handle($fixture['actor'], new ContributionPaymentData(
        member: $fixture['member'],
        enrolment: $fixture['enrolment'],
        plan: $fixture['plan'],
        amountMinor: 10000,
        currencyCode: 'GHS',
        paymentMethod: ContributionPaymentMethod::Cash,
        paymentDate: CarbonImmutable::today(),
        sourceWallet: $fixture['source'],
        destinationWallet: $fixture['destination'],
        allocationStrategy: PaymentAllocationStrategy::OldestDueFirst,
        idempotencyKey: 'payment-reverse-original',
    ));
    $reversal = app(ReverseContributionPayment::class)->handle(
        $payment,
        $fixture['actor'],
        'Cash entry corrected',
        'payment-reversal-one',
    );

    expect($payment->refresh()->status)->toBe(ContributionPaymentStatus::Reversed)
        ->and($payment->receipt->refresh()->status)->toBe(ReceiptStatus::Reversed)
        ->and($reversal->receipt->receipt_number)->not->toBe($payment->receipt->receipt_number)
        ->and($fixture['obligation']->refresh()->satisfaction_status)->toBe(ObligationSatisfactionStatus::Unsatisfied)
        ->and($fixture['obligation']->outstanding_amount_minor)->toBe(10000)
        ->and(app(CalculateWalletBalance::class)->calculate($fixture['source'])->displayBalanceMinor)->toBe(0)
        ->and(app(CalculateWalletBalance::class)->calculate($fixture['destination'])->displayBalanceMinor)->toBe(0);
});

test('collection factories preserve tenant ancestry', function () {
    $payment = ContributionPayment::factory()->create();
    $allocation = ContributionPaymentAllocation::factory()->create();
    $receipt = Receipt::factory()->create();

    expect($payment->member->organisation_id)->toBe($payment->organisation_id)
        ->and($payment->sourceWallet->organisation_id)->toBe($payment->organisation_id)
        ->and($payment->destinationWallet->organisation_id)->toBe($payment->organisation_id)
        ->and($allocation->obligation->organisation_id)->toBe($allocation->organisation_id)
        ->and($allocation->member_id)->toBe($allocation->payment->member_id)
        ->and($receipt->payment->organisation_id)->toBe($receipt->organisation_id)
        ->and($receipt->financial_transaction_id)->toBe($receipt->payment->financial_transaction_id);
});

test('automatic allocation rejects prohibited partial payments without financial effects', function () {
    $fixture = collectionFixture();
    $fixture['plan']->update(['allow_partial_payments' => false]);

    expect(fn () => app(RecordContributionPayment::class)->handle(
        $fixture['actor'],
        new ContributionPaymentData(
            member: $fixture['member'],
            enrolment: $fixture['enrolment'],
            plan: $fixture['plan']->refresh(),
            amountMinor: 5000,
            currencyCode: 'GHS',
            paymentMethod: ContributionPaymentMethod::Cash,
            paymentDate: CarbonImmutable::today(),
            sourceWallet: $fixture['source'],
            destinationWallet: $fixture['destination'],
            allocationStrategy: PaymentAllocationStrategy::OldestDueFirst,
            idempotencyKey: 'partial-disabled',
        ),
    ))->toThrow(InvalidContributionPayment::class);

    expect(ContributionPayment::query()->count())->toBe(0)
        ->and($fixture['obligation']->refresh()->outstanding_amount_minor)->toBe(10000)
        ->and(app(CalculateWalletBalance::class)->calculate($fixture['destination'])->displayBalanceMinor)->toBe(0);
});

test('conflicting payment idempotency payload is rejected', function () {
    $fixture = collectionFixture();
    $base = fn (int $amount): ContributionPaymentData => new ContributionPaymentData(
        member: $fixture['member'],
        enrolment: null,
        plan: null,
        amountMinor: $amount,
        currencyCode: 'GHS',
        paymentMethod: ContributionPaymentMethod::Cash,
        paymentDate: CarbonImmutable::today(),
        sourceWallet: $fixture['source'],
        destinationWallet: $fixture['destination'],
        allocationStrategy: PaymentAllocationStrategy::LeaveUnallocated,
        idempotencyKey: 'payment-conflict',
    );
    app(RecordContributionPayment::class)->handle($fixture['actor'], $base(10000));

    expect(fn () => app(RecordContributionPayment::class)->handle(
        $fixture['actor'],
        $base(20000),
    ))->toThrow(InvalidContributionPayment::class);

    expect(ContributionPayment::query()->count())->toBe(1);
});
