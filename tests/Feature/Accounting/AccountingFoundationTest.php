<?php

use App\Domain\Accounting\Actions\CloseAccountingPeriod;
use App\Domain\Accounting\Actions\CreateAccountingPeriod;
use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Accounting\Actions\ReopenAccountingPeriod;
use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountingPeriodStatus;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CloseWallet;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Actions\ReinstateWallet;
use App\Domain\Wallets\Actions\SuspendWallet;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Exceptions\InvalidWalletOperation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;

function establishAccountingContext(
    User $actor,
    Organisation $organisation,
    SystemRole $role = SystemRole::Treasurer,
): void {
    $assignment = OrganisationUserRole::factory()
        ->for($actor)
        ->for($organisation)
        ->state(['role_id' => Role::findByName($role->value)->getKey()])
        ->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment);
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('default chart is complete idempotent and uses organisation currency', function () {
    $organisation = Organisation::factory()->create(['currency_code' => 'GHS']);
    $actor = User::factory()->create();
    establishAccountingContext($actor, $organisation);
    $action = app(CreateDefaultChartOfAccounts::class);

    $first = $action->handle($organisation, $actor);
    LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '1100')->update([
        'description' => 'Owner supplied description',
    ]);
    $second = $action->handle($organisation, $actor);

    expect($first)->toHaveCount(28)
        ->and($second)->toHaveCount(28)
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->count())->toBe(28)
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->pluck('code')->unique())->toHaveCount(28)
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->pluck('currency_code')->unique()->all())->toBe(['GHS'])
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '1100')->value('description'))
        ->toBe('Owner supplied description')
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::MemberSavingsLiability)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::CashOverageLiability)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::CashShortageExpense)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::MemberLoanReceivable)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::LoanInterestIncome)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::LoanFeeIncome)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::LoanPenaltyIncome)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::WelfareClaimsPayable)->exists())
        ->toBeTrue()
        ->and(LedgerAccount::query()->whereBelongsTo($organisation)->where('purpose', AccountPurpose::WelfareBenefitExpense)->exists())
        ->toBeTrue();
});

test('account categories define stable normal balances', function () {
    expect(AccountCategory::Asset->normalBalance()->value)->toBe('debit')
        ->and(AccountCategory::Expense->normalBalance()->value)->toBe('debit')
        ->and(AccountCategory::Liability->normalBalance()->value)->toBe('credit')
        ->and(AccountCategory::Equity->normalBalance()->value)->toBe('credit')
        ->and(AccountCategory::Income->normalBalance()->value)->toBe('credit');
});

test('member wallet maps to the system liability and lifecycle preserves history', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishAccountingContext($actor, $organisation);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $actor);
    $member = Member::factory()->active()->for($organisation)->create();

    $wallet = app(CreateMemberWallet::class)->handle(
        $actor,
        $member,
        WalletType::MemberSavings,
        'Primary Savings',
        'MEM-'.$member->membership_number,
    );
    $wallet = app(SuspendWallet::class)->handle($wallet);
    $wallet = app(ReinstateWallet::class)->handle($wallet);
    $wallet = app(CloseWallet::class)->handle($wallet);

    expect($wallet->ledgerAccount->code)->toBe('2000')
        ->and($wallet->ledgerAccount->category)->toBe(AccountCategory::Liability)
        ->and($wallet->status)->toBe(WalletStatus::Closed)
        ->and($wallet->active_owner_key)->toBeNull()
        ->and($wallet->getAttributes())->not->toHaveKey('balance');
});

test('duplicate active member wallet is rejected by a durable unique key', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishAccountingContext($actor, $organisation);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $actor);
    $member = Member::factory()->active()->for($organisation)->create();
    $action = app(CreateMemberWallet::class);
    $action->handle($actor, $member, WalletType::MemberSavings, 'Savings', 'SAV-1');

    $action->handle($actor, $member, WalletType::MemberSavings, 'Duplicate', 'SAV-2');
})->throws(InvalidWalletOperation::class);

test('accounting periods reject overlaps and require reason to reopen', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishAccountingContext($actor, $organisation);
    $create = app(CreateAccountingPeriod::class);
    $period = $create->handle(
        'FY 2026',
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-12-31'),
    );
    $period = app(CloseAccountingPeriod::class)->handle($period, $actor);

    expect($period->status)->toBe(AccountingPeriodStatus::Closed);

    app(ReopenAccountingPeriod::class)->handle($period, $actor, '');
})->throws(InvalidFinancialOperation::class);

test('accounting period overlap is rejected', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishAccountingContext($actor, $organisation);
    $create = app(CreateAccountingPeriod::class);
    $create->handle('First', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-06-30'));

    $create->handle('Overlap', CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-12-31'));
})->throws(InvalidFinancialOperation::class);
