<?php

use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionStatus;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Exceptions\InvalidFinancialOperation;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\CalculateLedgerAccountBalance;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Services\CalculateWalletBalance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function postAccountingFixture(string $key = 'posting-key-1'): array
{
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'UNITY']);
    establishAccountingContext($actor, $organisation);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $actor);
    $member = Member::factory()->active()->for($organisation)->create();
    $wallet = app(CreateMemberWallet::class)->handle(
        $actor,
        $member,
        WalletType::MemberSavings,
        'Savings',
        'SAV-'.$member->membership_number,
    );
    $cash = LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '1100')->firstOrFail();
    $liability = LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '2000')->firstOrFail();
    $data = new TransactionData(
        branch: null,
        transactionType: FinancialTransactionType::Adjustment,
        currencyCode: 'GHS',
        transactionDate: CarbonImmutable::parse('2026-07-25'),
        description: 'Controlled accounting posting',
        idempotencyKey: $key,
    );
    $entries = [
        new TransactionEntryData($cash, EntryType::Debit, 12500),
        new TransactionEntryData($liability, EntryType::Credit, 12500, member: $member, wallet: $wallet),
    ];

    return compact('actor', 'organisation', 'member', 'wallet', 'cash', 'liability', 'data', 'entries');
}

test('central service posts a balanced immutable transaction with a sequence number', function () {
    $fixture = postAccountingFixture();
    $transaction = app(PostFinancialTransaction::class)->handle(
        $fixture['actor'],
        $fixture['data'],
        $fixture['entries'],
    );

    expect($transaction->status)->toBe(FinancialTransactionStatus::Posted)
        ->and($transaction->transaction_number)->toBe('UNITY-TXN-2026-000001')
        ->and($transaction->entries)->toHaveCount(2)
        ->and($transaction->entries->sum('amount_minor'))->toBe(25000)
        ->and($transaction->posted_by_user_id)->toBe($fixture['actor']->id);

    $transaction->update(['description' => 'Tampered']);
})->throws(LogicException::class);

test('unbalanced zero and one-sided postings are rejected without persistence', function (array $entries) {
    $fixture = postAccountingFixture(fake()->uuid());
    $resolved = array_map(fn (array $entry): TransactionEntryData => new TransactionEntryData(
        $entry[0] === 'cash' ? $fixture['cash'] : $fixture['liability'],
        EntryType::from($entry[1]),
        $entry[2],
    ), $entries);

    try {
        app(PostFinancialTransaction::class)->handle($fixture['actor'], $fixture['data'], $resolved);
    } finally {
        expect(FinancialTransaction::query()->whereBelongsTo($fixture['organisation'])->count())->toBe(0);
    }
})->with([
    'unbalanced' => [[['cash', 'debit', 100], ['liability', 'credit', 99]]],
    'zero' => [[['cash', 'debit', 0], ['liability', 'credit', 0]]],
    'one-sided' => [[['cash', 'debit', 100], ['liability', 'debit', 100]]],
])->throws(InvalidFinancialOperation::class);

test('idempotent retries return one transaction and conflicting reuse fails', function () {
    $fixture = postAccountingFixture();
    $service = app(PostFinancialTransaction::class);
    $first = $service->handle($fixture['actor'], $fixture['data'], $fixture['entries']);
    $second = $service->handle($fixture['actor'], $fixture['data'], array_reverse($fixture['entries']));

    expect($second->is($first))->toBeTrue()
        ->and(FinancialTransaction::query()->whereBelongsTo($fixture['organisation'])->count())->toBe(1)
        ->and($first->entries()->count())->toBe(2);

    $conflicting = new TransactionData(
        branch: null,
        transactionType: FinancialTransactionType::Adjustment,
        currencyCode: 'GHS',
        transactionDate: CarbonImmutable::parse('2026-07-25'),
        description: 'Different payload',
        idempotencyKey: $fixture['data']->idempotencyKey,
    );
    $service->handle($fixture['actor'], $conflicting, $fixture['entries']);
})->throws(InvalidFinancialOperation::class);

test('reversal creates exact opposite entries and returns balances to zero', function () {
    $fixture = postAccountingFixture();
    $original = app(PostFinancialTransaction::class)->handle(
        $fixture['actor'],
        $fixture['data'],
        $fixture['entries'],
    );
    $reversal = app(ReverseFinancialTransaction::class)->handle(
        $original,
        $fixture['actor'],
        'Incorrect opening classification',
        'reversal-key-1',
    );

    expect($original->refresh()->status)->toBe(FinancialTransactionStatus::Reversed)
        ->and($reversal->original_transaction_id)->toBe($original->id)
        ->and($reversal->entries->pluck('entry_type')->all())->toBe([
            EntryType::Credit,
            EntryType::Debit,
        ])
        ->and(app(CalculateLedgerAccountBalance::class)->calculate($fixture['cash'])->displayBalanceMinor)->toBe(0)
        ->and(app(CalculateWalletBalance::class)->calculate($fixture['wallet'])->displayBalanceMinor)->toBe(0)
        ->and($original->entries()->count())->toBe(2);
});

test('credit normal wallets increase through credits and asset accounts through debits', function () {
    $fixture = postAccountingFixture();
    app(PostFinancialTransaction::class)->handle(
        $fixture['actor'],
        $fixture['data'],
        $fixture['entries'],
    );

    expect(app(CalculateLedgerAccountBalance::class)->calculate($fixture['cash'])->displayBalanceMinor)->toBe(12500)
        ->and(app(CalculateWalletBalance::class)->calculate($fixture['wallet'])->displayBalanceMinor)->toBe(12500);
});
