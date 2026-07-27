<?php

use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Accounting\Models\FinancialTransaction;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Tests\TestCase;

function selectAccountingApiContext(
    TestCase $test,
    User $user,
    Organisation $organisation,
    SystemRole $role,
    ?Branch $branch = null,
): void {
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName($role->value)->getKey(),
        ])->create();

    $test->actingAs($user)->postJson(route('api.v1.auth.context.store'), [
        'organisation_uuid' => $organisation->public_id,
        'branch_uuid' => $branch?->public_id,
        'role_assignment_uuid' => $assignment->public_id,
    ])->assertSuccessful();
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('treasurer creates accounts and wallets using public identifiers only', function () {
    $treasurer = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectAccountingApiContext($this, $treasurer, $organisation, SystemRole::Treasurer);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $treasurer);
    $member = Member::factory()->active()->for($organisation)->create();

    $this->postJson(route('api.v1.ledger-accounts.store'), [
        'code' => '9001',
        'name' => 'Migration Control',
        'category' => 'asset',
        'purpose' => 'other',
        'allows_direct_posting' => true,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.normal_balance', 'debit')
        ->assertJsonMissingPath('data.organisation_id');

    $wallet = $this->postJson(route('api.v1.wallets.store'), [
        'owner_type' => 'member',
        'owner_uuid' => $member->public_id,
        'wallet_type' => 'member_savings',
        'name' => 'Primary Savings',
        'code' => 'MEMBER-'.$member->membership_number,
    ])->assertCreated()
        ->assertJsonPath('data.ledger_account.code', '2000')
        ->assertJsonMissingPath('data.ledger_account_id');

    $this->getJson(route('api.v1.wallets.balance', $wallet->json('data.uuid')))
        ->assertSuccessful()
        ->assertJsonPath('data.display_balance_minor', 0);
});

test('controlled posting endpoint requires idempotency and returns balanced detail', function () {
    $treasurer = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'UNITY']);
    selectAccountingApiContext($this, $treasurer, $organisation, SystemRole::Treasurer);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $treasurer);
    $cash = LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '1100')->firstOrFail();
    $equity = LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '3000')->firstOrFail();
    $payload = [
        'transaction_type' => 'adjustment',
        'currency_code' => 'GHS',
        'transaction_date' => now()->toDateString(),
        'description' => 'Controlled API adjustment',
        'entries' => [
            ['account_uuid' => $cash->public_id, 'entry_type' => 'debit', 'amount_minor' => 10000],
            ['account_uuid' => $equity->public_id, 'entry_type' => 'credit', 'amount_minor' => 10000],
        ],
    ];

    $this->postJson(route('api.v1.transactions.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    $response = $this->withHeader('Idempotency-Key', 'api-posting-1')
        ->postJson(route('api.v1.transactions.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.transaction_number', 'UNITY-TXN-'.now()->year.'-000001')
        ->assertJsonPath('data.debit_total_minor', 10000)
        ->assertJsonPath('data.credit_total_minor', 10000)
        ->assertJsonMissingPath('data.request_fingerprint');

    $this->getJson(route('api.v1.transactions.show', $response->json('data.uuid')))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.entries')
        ->assertJsonMissingPath('data.entries.0.ledger_account_id');
});

test('auditor is read only and member role cannot use staff accounting APIs', function () {
    $organisation = Organisation::factory()->create();
    $auditor = User::factory()->create();
    selectAccountingApiContext($this, $auditor, $organisation, SystemRole::Auditor);
    $this->getJson(route('api.v1.ledger-accounts.index'))->assertSuccessful();
    $this->postJson(route('api.v1.ledger-accounts.store'), [])->assertForbidden();
    $this->postJson(route('api.v1.transactions.store'), [])->assertForbidden();

    $memberUser = User::factory()->create();
    selectAccountingApiContext($this, $memberUser, $organisation, SystemRole::Member);
    $this->getJson(route('api.v1.wallets.index'))->assertForbidden();
});

test('branch context cannot discover another branch wallets or transactions', function () {
    $manager = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    selectAccountingApiContext($this, $manager, $organisation, SystemRole::BranchManager, $branch);
    $foreignWallet = Wallet::factory()->create([
        'organisation_id' => $organisation->id,
        'branch_id' => $otherBranch->id,
    ]);
    $foreignTransaction = FinancialTransaction::factory()->for($organisation)->create([
        'branch_id' => $otherBranch->id,
    ]);

    $this->getJson(route('api.v1.wallets.show', $foreignWallet->public_id))->assertNotFound();
    $this->getJson(route('api.v1.transactions.show', $foreignTransaction->public_id))->assertNotFound();
});

test('trial balance and member wallet summary exclude contribution obligations', function () {
    $treasurer = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectAccountingApiContext($this, $treasurer, $organisation, SystemRole::Treasurer);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $treasurer);
    $member = Member::factory()->active()->for($organisation)->create();
    $wallet = $this->postJson(route('api.v1.wallets.store'), [
        'owner_type' => 'member',
        'owner_uuid' => $member->public_id,
        'wallet_type' => 'member_savings',
        'name' => 'Savings',
        'code' => 'SUMMARY-'.$member->membership_number,
    ])->assertCreated();

    $this->getJson(route('api.v1.accounting.trial-balance'))
        ->assertSuccessful()
        ->assertJsonPath('data.totals.is_balanced', true);
    $this->getJson(route('api.v1.members.wallets.index', $member->public_id))
        ->assertSuccessful()
        ->assertJsonFragment(['uuid' => $wallet->json('data.uuid')])
        ->assertJsonMissingPath('data.contribution_obligations')
        ->assertJsonMissingPath('data.outstanding_expected_contributions');
});
