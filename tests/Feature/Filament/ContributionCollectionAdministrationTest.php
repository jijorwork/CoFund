<?php

use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Collections\Actions\PrepareReceiptReissue;
use App\Domain\Collections\Actions\RecordContributionPayment;
use App\Domain\Collections\Actions\ReverseContributionPayment;
use App\Domain\Collections\Data\ContributionAllocationData;
use App\Domain\Collections\Data\ContributionPaymentData;
use App\Domain\Collections\Enums\ContributionPaymentMethod;
use App\Domain\Collections\Enums\PaymentAllocationStrategy;
use App\Domain\Collections\Exceptions\InvalidReceiptOperation;
use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Collections\Models\ContributionPaymentAllocation;
use App\Domain\Collections\Models\Receipt;
use App\Domain\Collections\Services\AllocateContributionPayment;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Actions\CreateOrganisationWallet;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Filament\Resources\ContributionPayments\ContributionPaymentResource;
use App\Filament\Resources\ContributionPayments\Pages\ListContributionPayments;
use App\Filament\Resources\ContributionPayments\Pages\ViewContributionPayment;
use App\Filament\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Resources\Receipts\Pages\ViewReceipt;
use App\Filament\Resources\Receipts\ReceiptResource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

function establishContributionCollectionFilamentContext(
    User $user,
    Organisation $organisation,
    SystemRole $role = SystemRole::OrganisationAdmin,
    ?Branch $branch = null,
): OrganisationUserRole {
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName($role->value)->getKey(),
        ])
        ->create();

    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    return $assignment;
}

/** @param array<string, mixed> $overrides */
function receiptForPayment(ContributionPayment $payment, array $overrides = []): Receipt
{
    return Receipt::factory()->create([
        'organisation_id' => $payment->organisation_id,
        'branch_id' => $payment->branch_id,
        'contribution_payment_id' => $payment->id,
        'financial_transaction_id' => $payment->financial_transaction_id,
        'member_id' => $payment->member_id,
        'payment_method' => $payment->payment_method->value,
        'amount_minor' => $payment->amount_minor,
        'currency_code' => $payment->currency_code,
        ...$overrides,
    ]);
}

/** @return array{actor: User, organisation: Organisation, payment: ContributionPayment} */
function recordedContributionPaymentForFilament(): array
{
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'code' => 'UNITY',
        'currency_code' => 'GHS',
        'timezone' => 'Africa/Accra',
    ]);
    establishContributionCollectionFilamentContext(
        $actor,
        $organisation,
        SystemRole::Treasurer,
    );
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $actor);
    $source = app(CreateOrganisationWallet::class)->handle(
        $actor,
        WalletType::OfficeCash,
        'Office Cash',
        'OFFICE-CASH',
    );
    $member = Member::factory()->active()->for($organisation)->create();
    $destination = app(CreateMemberWallet::class)->handle(
        $actor,
        $member,
        WalletType::MemberSavings,
        'Member Savings',
        'SAV-'.$member->membership_number,
    );
    $payment = app(RecordContributionPayment::class)->handle(
        $actor,
        new ContributionPaymentData(
            member: $member,
            enrolment: null,
            plan: null,
            amountMinor: 10000,
            currencyCode: 'GHS',
            paymentMethod: ContributionPaymentMethod::Cash,
            paymentDate: CarbonImmutable::today(),
            sourceWallet: $source,
            destinationWallet: $destination,
            allocationStrategy: PaymentAllocationStrategy::LeaveUnallocated,
            idempotencyKey: 'payment-for-filament-reversal',
        ),
    );

    return compact('actor', 'organisation', 'payment');
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('payments and receipts are isolated by organisation and exact active branch', function () {
    $actor = User::factory()->create();
    $visiblePayment = ContributionPayment::factory()->create();
    $organisation = $visiblePayment->organisation;
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    DB::table('contribution_payments')
        ->where('id', $visiblePayment->id)
        ->update(['branch_id' => $activeBranch->id]);
    $visiblePayment->refresh();
    $visibleReceipt = receiptForPayment($visiblePayment);
    $otherBranchPayment = ContributionPayment::factory()->create([
        'organisation_id' => $organisation->id,
        'branch_id' => $otherBranch->id,
    ]);
    $otherBranchReceipt = receiptForPayment($otherBranchPayment);
    $foreignPayment = ContributionPayment::factory()->create();
    $foreignReceipt = receiptForPayment($foreignPayment);
    establishContributionCollectionFilamentContext(
        $actor,
        $organisation,
        branch: $activeBranch,
    );

    $this->actingAs($actor);

    Livewire::test(ListContributionPayments::class)
        ->assertCanSeeTableRecords([$visiblePayment])
        ->assertCanNotSeeTableRecords([$otherBranchPayment, $foreignPayment]);

    Livewire::test(ListReceipts::class)
        ->assertCanSeeTableRecords([$visibleReceipt])
        ->assertCanNotSeeTableRecords([$otherBranchReceipt, $foreignReceipt]);

    $loadedPayment = ContributionPaymentResource::getEloquentQuery()
        ->findOrFail($visiblePayment->id);
    $loadedReceipt = ReceiptResource::getEloquentQuery()
        ->findOrFail($visibleReceipt->id);

    expect($loadedPayment)
        ->relationLoaded('member')->toBeTrue()
        ->relationLoaded('contributionPlan')->toBeTrue()
        ->relationLoaded('financialTransaction')->toBeTrue()
        ->relationLoaded('sourceWallet')->toBeTrue()
        ->relationLoaded('destinationWallet')->toBeTrue()
        ->relationLoaded('collectedBy')->toBeTrue()
        ->relationLoaded('receipt')->toBeTrue()
        ->and($loadedPayment->allocations_count)->toBeInt()
        ->and($loadedReceipt)
        ->relationLoaded('member')->toBeTrue()
        ->relationLoaded('issuedBy')->toBeTrue()
        ->relationLoaded('payment')->toBeTrue()
        ->relationLoaded('financialTransaction')->toBeTrue();
});

test('payment and receipt direct urls reject foreign records and missing permissions', function () {
    $actor = User::factory()->create();
    $payment = ContributionPayment::factory()->create();
    $organisation = $payment->organisation;
    $assignment = establishContributionCollectionFilamentContext($actor, $organisation);
    $foreignPayment = ContributionPayment::factory()->create();
    $foreignReceipt = receiptForPayment($foreignPayment);
    $session = [
        'active_context' => [
            'organisation_public_id' => $organisation->public_id,
            'branch_public_id' => null,
            'role_assignment_public_id' => $assignment->public_id,
        ],
    ];

    $this->actingAs($actor)
        ->withSession($session)
        ->get(ContributionPaymentResource::getUrl('view', [
            'record' => $foreignPayment->public_id,
        ]))
        ->assertNotFound();

    $this->withSession($session)
        ->get(ReceiptResource::getUrl('view', [
            'record' => $foreignReceipt->public_id,
        ]))
        ->assertNotFound();

    $assignment->role->revokePermissionTo(
        FoundationPermission::ContributionPaymentsView->value,
    );

    expect(ContributionPaymentResource::canViewAny())->toBeFalse();

    $this->withSession($session)
        ->get(ContributionPaymentResource::getUrl())
        ->assertForbidden();

    $assignment->role->revokePermissionTo(FoundationPermission::ReceiptsView->value);
    app(TenantContext::class)->setRoleAssignment(
        $assignment->refresh()->load(['role', 'user']),
    );

    expect(ReceiptResource::canViewAny())->toBeFalse();

    $this->withSession($session)
        ->get(ReceiptResource::getUrl())
        ->assertForbidden();
});

test('collectors only see their own payments and contribution receipts', function () {
    $collector = User::factory()->create();
    $otherCollector = User::factory()->create();
    $ownPayment = ContributionPayment::factory()->create([
        'collected_by_user_id' => $collector->id,
    ]);
    $organisation = $ownPayment->organisation;
    $otherPayment = ContributionPayment::factory()->create([
        'organisation_id' => $organisation->id,
        'collected_by_user_id' => $otherCollector->id,
    ]);
    $ownReceipt = receiptForPayment($ownPayment);
    $otherReceipt = receiptForPayment($otherPayment);
    establishContributionCollectionFilamentContext(
        $collector,
        $organisation,
        SystemRole::Collector,
    );

    $this->actingAs($collector);

    Livewire::test(ListContributionPayments::class)
        ->assertCanSeeTableRecords([$ownPayment])
        ->assertCanNotSeeTableRecords([$otherPayment]);

    Livewire::test(ListReceipts::class)
        ->assertCanSeeTableRecords([$ownReceipt])
        ->assertCanNotSeeTableRecords([$otherReceipt]);
});

test('payment and receipt resources are responsive searchable and immutable', function () {
    $actor = User::factory()->create();
    $payment = ContributionPayment::factory()->create([
        'payment_number' => 'UNITY-PAY-2026-000123',
        'amount_minor' => 12500,
    ]);
    $organisation = $payment->organisation;
    $receipt = receiptForPayment($payment, [
        'receipt_number' => 'UNITY-RCP-2026-000123',
    ]);
    establishContributionCollectionFilamentContext($actor, $organisation);
    $this->actingAs($actor);

    $paymentComponent = Livewire::test(ListContributionPayments::class)
        ->searchTable('UNITY-PAY-2026-000123')
        ->assertCanSeeTableRecords([$payment]);
    $receiptComponent = Livewire::test(ListReceipts::class)
        ->searchTable('UNITY-RCP-2026-000123')
        ->assertCanSeeTableRecords([$receipt]);

    expect($paymentComponent->instance()->getTable()->getColumn('payment_method')?->getVisibleFrom())
        ->toBe('md')
        ->and($paymentComponent->instance()->getTable()->getColumn('allocated_amount_minor')?->getVisibleFrom())
        ->toBe('lg')
        ->and($paymentComponent->instance()->getTable()->getColumn('collectedBy.name')?->getVisibleFrom())
        ->toBe('xl')
        ->and($receiptComponent->instance()->getTable()->getColumn('receipt_type')?->getVisibleFrom())
        ->toBe('md')
        ->and($receiptComponent->instance()->getTable()->getColumn('payment_method')?->getVisibleFrom())
        ->toBe('lg')
        ->and($receiptComponent->instance()->getTable()->getColumn('issuedBy.name')?->getVisibleFrom())
        ->toBe('xl')
        ->and(ContributionPaymentResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(ReceiptResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(ContributionPaymentResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit'])
        ->and(ReceiptResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit'])
        ->and(ContributionPaymentResource::canCreate())->toBeFalse()
        ->and(ContributionPaymentResource::canEdit($payment))->toBeFalse()
        ->and(ContributionPaymentResource::canDelete($payment))->toBeFalse()
        ->and(ReceiptResource::canCreate())->toBeFalse()
        ->and(ReceiptResource::canEdit($receipt))->toBeFalse()
        ->and(ReceiptResource::canDelete($receipt))->toBeFalse()
        ->and(ContributionPaymentResource::getGlobalSearchResultUrl($payment))
        ->toContain($payment->public_id)
        ->and(ReceiptResource::getGlobalSearchResultUrl($receipt))
        ->toContain($receipt->public_id);
});

test('payment detail exposes immutable allocation and accounting traces without sensitive verification data', function () {
    $actor = User::factory()->create();
    $payment = ContributionPayment::factory()->fullyAllocated()->create([
        'payment_number' => 'UNITY-PAY-2026-000999',
        'amount_minor' => 10000,
    ]);
    $organisation = $payment->organisation;
    $obligation = ContributionObligation::factory()->create([
        'organisation_id' => $organisation->id,
        'member_plan_enrolment_id' => $payment->member_plan_enrolment_id,
        'contribution_plan_id' => $payment->contribution_plan_id,
        'member_id' => $payment->member_id,
        'sequence_number' => 7,
    ]);
    ContributionPaymentAllocation::factory()->create([
        'organisation_id' => $organisation->id,
        'contribution_payment_id' => $payment->id,
        'contribution_obligation_id' => $obligation->id,
        'member_plan_enrolment_id' => $payment->member_plan_enrolment_id,
        'member_id' => $payment->member_id,
        'amount_minor' => 10000,
        'currency_code' => 'GHS',
        'allocation_order' => 1,
    ]);
    $receipt = receiptForPayment($payment);
    $verificationCode = $receipt->verification_code;
    establishContributionCollectionFilamentContext($actor, $organisation);

    $this->actingAs($actor);

    $paymentComponent = Livewire::test(ViewContributionPayment::class, [
        'record' => $payment->public_id,
    ])
        ->assertSee('UNITY-PAY-2026-000999')
        ->assertSee('GHS 100.00')
        ->assertSee('Allocations')
        ->assertSee('Accounting trace');

    Livewire::test(ViewReceipt::class, ['record' => $receipt->public_id])
        ->assertSee($receipt->receipt_number)
        ->assertSee('GHS 100.00')
        ->assertSee('Transaction trace')
        ->assertDontSee($verificationCode)
        ->assertDontSee($receipt->verification_hash);

    $loadedPayment = $paymentComponent->instance()->getRecord();

    expect($loadedPayment)
        ->toBeInstanceOf(ContributionPayment::class)
        ->relationLoaded('allocations')->toBeTrue()
        ->and($loadedPayment->allocations)->toHaveCount(1)
        ->and($loadedPayment->allocations->first()?->relationLoaded('obligation'))
        ->toBeTrue();
});

test('allocation preview uses production rules without changing obligations', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['timezone' => 'Africa/Accra']);
    establishContributionCollectionFilamentContext(
        $actor,
        $organisation,
        SystemRole::Treasurer,
    );
    $member = Member::factory()->active()->for($organisation)->create();
    $plan = ContributionPlan::factory()->active()->for($organisation)->create([
        'allow_partial_payments' => true,
        'allow_advance_payments' => true,
        'allow_overpayments' => false,
    ]);
    $enrolment = MemberPlanEnrolment::factory()
        ->active()
        ->for($plan, 'contributionPlan')
        ->for($member)
        ->create();
    $first = ContributionObligation::factory()
        ->overdue()
        ->for($enrolment, 'enrolment')
        ->create([
            'organisation_id' => $organisation->id,
            'contribution_plan_id' => $plan->id,
            'member_id' => $member->id,
            'sequence_number' => 1,
            'expected_amount_minor' => 10000,
            'outstanding_amount_minor' => 10000,
        ]);
    $second = ContributionObligation::factory()
        ->due()
        ->for($enrolment, 'enrolment')
        ->create([
            'organisation_id' => $organisation->id,
            'contribution_plan_id' => $plan->id,
            'member_id' => $member->id,
            'sequence_number' => 2,
            'expected_amount_minor' => 8000,
            'outstanding_amount_minor' => 8000,
        ]);

    $preview = app(AllocateContributionPayment::class)->preview(
        new ContributionAllocationData(
            enrolment: $enrolment,
            plan: $plan,
            amountMinor: 15000,
            allocationStrategy: PaymentAllocationStrategy::OldestDueFirst,
        ),
    );

    expect($preview->allocatedAmountMinor)->toBe(15000)
        ->and($preview->unallocatedAmountMinor)->toBe(0)
        ->and($preview->allocations)->toHaveCount(2)
        ->and($preview->allocations[0]->obligation->is($first))->toBeTrue()
        ->and($preview->allocations[0]->amountMinor)->toBe(10000)
        ->and($preview->allocations[1]->obligation->is($second))->toBeTrue()
        ->and($preview->allocations[1]->amountMinor)->toBe(5000)
        ->and($first->refresh()->outstanding_amount_minor)->toBe(10000)
        ->and($second->refresh()->outstanding_amount_minor)->toBe(8000)
        ->and(ContributionPayment::query()->count())->toBe(0)
        ->and(ContributionPaymentAllocation::query()->count())->toBe(0);
});

test('collection action is permission gated tenant safe and idempotent', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'code' => 'UNITY',
        'currency_code' => 'GHS',
    ]);
    establishContributionCollectionFilamentContext(
        $actor,
        $organisation,
        SystemRole::Treasurer,
    );
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $actor);
    $source = app(CreateOrganisationWallet::class)->handle(
        $actor,
        WalletType::OfficeCash,
        'Office Cash',
        'OFFICE-CASH',
    );
    $member = Member::factory()->active()->for($organisation)->create();
    $idempotencyKey = 'filament-collection-idempotency';
    $form = [
        'member_id' => $member->id,
        'enrolment_id' => null,
        'amount_minor' => 10000,
        'payment_method' => ContributionPaymentMethod::Cash->value,
        'source_wallet_id' => $source->id,
        'allocation_strategy' => PaymentAllocationStrategy::LeaveUnallocated->value,
        'allocations' => [],
        'payment_date' => today()->toDateString(),
        'external_reference' => 'CASHBOOK-101',
        'payer_name' => 'Member payer',
        'payer_phone' => '+233200000001',
        'notes' => 'Received at the office.',
        'idempotency_key' => $idempotencyKey,
        'organisation_id' => Organisation::factory()->create()->id,
        'status' => 'reversed',
    ];

    $this->actingAs($actor);

    Livewire::test(ListContributionPayments::class)
        ->assertActionVisible(TestAction::make('collectPayment')->table())
        ->callAction(
            TestAction::make('collectPayment')->table(),
            data: $form,
        )
        ->assertHasNoFormErrors()
        ->assertNotified('Contribution collected');

    Livewire::test(ListContributionPayments::class)
        ->callAction(
            TestAction::make('collectPayment')->table(),
            data: $form,
        )
        ->assertHasNoFormErrors()
        ->assertNotified('Contribution collected');

    $payment = ContributionPayment::query()
        ->whereBelongsTo($organisation)
        ->where('idempotency_key', $idempotencyKey)
        ->sole();

    expect(ContributionPayment::query()
        ->whereBelongsTo($organisation)
        ->where('idempotency_key', $idempotencyKey)
        ->count())->toBe(1)
        ->and($payment->member->is($member))->toBeTrue()
        ->and($payment->sourceWallet->is($source))->toBeTrue()
        ->and($payment->amount_minor)->toBe(10000)
        ->and($payment->status->value)->toBe('unallocated')
        ->and($payment->external_reference)->toBe('CASHBOOK-101')
        ->and($payment->financialTransaction)->not->toBeNull()
        ->and($payment->receipt)->not->toBeNull();

    $auditor = User::factory()->create();
    establishContributionCollectionFilamentContext(
        $auditor,
        $organisation,
        SystemRole::Auditor,
    );
    $this->actingAs($auditor);

    Livewire::test(ListContributionPayments::class)
        ->assertActionHidden(TestAction::make('collectPayment')->table());
});

test('collection action rejects foreign members enrolments and source wallets', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['currency_code' => 'GHS']);
    $foreignOrganisation = Organisation::factory()->create(['currency_code' => 'GHS']);
    establishContributionCollectionFilamentContext(
        $actor,
        $organisation,
        SystemRole::Treasurer,
    );
    $member = Member::factory()->active()->for($organisation)->create();
    $foreignMember = Member::factory()->active()->for($foreignOrganisation)->create();
    $foreignEnrolment = MemberPlanEnrolment::factory()
        ->active()
        ->for(ContributionPlan::factory()->active()->for($foreignOrganisation), 'contributionPlan')
        ->for($foreignMember)
        ->create();
    $foreignWallet = Wallet::factory()->create([
        'organisation_id' => $foreignOrganisation->id,
        'wallet_type' => WalletType::OfficeCash,
        'status' => WalletStatus::Active,
    ]);
    $base = [
        'amount_minor' => 10000,
        'payment_method' => ContributionPaymentMethod::Cash->value,
        'allocation_strategy' => PaymentAllocationStrategy::LeaveUnallocated->value,
        'allocations' => [],
        'payment_date' => today()->toDateString(),
        'idempotency_key' => 'foreign-tamper',
    ];

    $this->actingAs($actor);

    Livewire::test(ListContributionPayments::class)
        ->callAction(TestAction::make('collectPayment')->table(), data: [
            ...$base,
            'member_id' => $foreignMember->id,
            'enrolment_id' => $foreignEnrolment->id,
            'source_wallet_id' => $foreignWallet->id,
        ])
        ->assertActionHalted();

    Livewire::test(ListContributionPayments::class)
        ->callAction(TestAction::make('collectPayment')->table(), data: [
            ...$base,
            'member_id' => $member->id,
            'enrolment_id' => $foreignEnrolment->id,
            'source_wallet_id' => $foreignWallet->id,
        ])
        ->assertActionHalted();

    expect(ContributionPayment::query()
        ->whereBelongsTo($organisation)
        ->exists())->toBeFalse();
});

test('payment reversal creates one idempotent compensating transaction and preserves originals', function () {
    $fixture = recordedContributionPaymentForFilament();
    $payment = $fixture['payment'];
    $reason = 'Cash payment was recorded against the wrong member.';
    $idempotencyKey = 'filament-reversal-idempotency';
    $this->actingAs($fixture['actor']);

    Livewire::test(ListContributionPayments::class)
        ->assertActionVisible(TestAction::make('reverse')->table($payment))
        ->callAction(TestAction::make('reverse')->table($payment), data: [
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Contribution payment reversed');

    $payment->refresh();
    $reversal = $payment->reversalPayment()->firstOrFail();
    $retry = app(ReverseContributionPayment::class)->handle(
        $payment,
        $fixture['actor'],
        $reason,
        $idempotencyKey,
    );

    expect($retry->is($reversal))->toBeTrue()
        ->and(ContributionPayment::query()
            ->whereBelongsTo($fixture['organisation'])
            ->count())->toBe(2)
        ->and(Receipt::query()
            ->whereBelongsTo($fixture['organisation'])
            ->count())->toBe(2)
        ->and($payment->status->value)->toBe('reversed')
        ->and($payment->reversal_reason)->toBe($reason)
        ->and($payment->receipt->status->value)->toBe('reversed')
        ->and($reversal->reversedPayment->is($payment))->toBeTrue()
        ->and($reversal->reversal_reason)->toBe($reason)
        ->and($reversal->receipt->receipt_type->value)->toBe('contribution_reversal')
        ->and($payment->financialTransaction->refresh()->status->value)
        ->toBe('reversed')
        ->and($payment->financialTransaction->reversal_transaction_id)
        ->toBe($reversal->financial_transaction_id);

    Livewire::test(ListContributionPayments::class)
        ->assertActionHidden(TestAction::make('reverse')->table($payment))
        ->assertActionHidden(TestAction::make('reverse')->table($reversal));
});

test('authorized receipt reissue downloads an escaped printable receipt with verification code', function () {
    CarbonImmutable::setTestNow('2026-07-27 12:30:00');
    $actor = User::factory()->create();
    $receipt = Receipt::factory()->create();
    $organisation = $receipt->organisation;
    $organisation->update(['name' => '<script>alert("unsafe")</script> Unity']);
    establishContributionCollectionFilamentContext(
        $actor,
        $organisation,
        SystemRole::Treasurer,
    );
    $prepared = app(PrepareReceiptReissue::class)->handle($receipt);
    $audit = AuditEvent::query()->where('event_key', 'receipt.reissued')->sole();
    expect($audit->organisation_id)->toBe($organisation->id)
        ->and($audit->actor_user_id)->toBe($actor->id)
        ->and($audit->subject_public_id)->toBe($receipt->public_id)
        ->and($audit->details['receipt_number'])->toBe($receipt->receipt_number);
    $expectedHtml = view('receipts.print', [
        'receiptView' => $prepared['view'],
        'reissuedAt' => $prepared['reissued_at'],
    ])->render();
    $filename = Str::slug($receipt->receipt_number).'.html';
    $this->actingAs($actor);

    Livewire::test(ListReceipts::class)
        ->assertActionVisible(TestAction::make('reissue')->table($receipt))
        ->callAction(TestAction::make('reissue')->table($receipt))
        ->assertFileDownloaded(
            $filename,
            $expectedHtml,
            'text/html; charset=UTF-8',
        );

    expect($expectedHtml)
        ->toContain($receipt->verification_code)
        ->toContain($receipt->receipt_number)
        ->toContain('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt; Unity')
        ->not->toContain('<script>alert("unsafe")</script>');

    $auditor = User::factory()->create();
    establishContributionCollectionFilamentContext(
        $auditor,
        $organisation,
        SystemRole::Auditor,
    );
    $this->actingAs($auditor);

    Livewire::test(ListReceipts::class)
        ->assertActionHidden(TestAction::make('reissue')->table($receipt));

});

test('receipt reissue domain action rejects records outside the active context', function () {
    $actor = User::factory()->create();
    $receipt = Receipt::factory()->create();
    $foreignReceipt = Receipt::factory()->create();
    establishContributionCollectionFilamentContext(
        $actor,
        $receipt->organisation,
        SystemRole::Treasurer,
    );

    expect(fn () => app(PrepareReceiptReissue::class)->handle($foreignReceipt))
        ->toThrow(InvalidReceiptOperation::class);
    expect(AuditEvent::query()->where('event_key', 'receipt.reissued')->count())->toBe(0);
});
