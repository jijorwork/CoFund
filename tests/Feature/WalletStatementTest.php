<?php

use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Accounting\Actions\ReverseFinancialTransaction;
use App\Domain\Accounting\Data\TransactionData;
use App\Domain\Accounting\Data\TransactionEntryData;
use App\Domain\Accounting\Enums\EntryType;
use App\Domain\Accounting\Enums\FinancialTransactionType;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Accounting\Services\PostFinancialTransaction;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CreateMemberWallet;
use App\Domain\Wallets\Enums\WalletType;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;
use Tests\TestCase;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function selectStatementApiContext(TestCase $test, User $actor, Organisation $organisation, SystemRole $role, ?Branch $branch = null): OrganisationUserRole
{
    $assignment = OrganisationUserRole::factory()->for($actor)->for($organisation)->state([
        'role_id' => Role::findByName($role->value)->getKey(),
        'branch_id' => $branch?->getKey(),
    ])->create();
    $test->actingAs($actor)->postJson(route('api.v1.auth.context.store'), [
        'organisation_uuid' => $organisation->public_id,
        'branch_uuid' => $branch?->public_id,
        'role_assignment_uuid' => $assignment->public_id,
    ])->assertSuccessful();

    return $assignment;
}

test('wallet statements derive opening, paged running, and closing balances from posted ledger entries', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'STMT']);
    $assignment = selectStatementApiContext($this, $actor, $organisation, SystemRole::Treasurer);
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch(null);
    $context->setRoleAssignment($assignment);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $actor);
    $member = Member::factory()->active()->for($organisation)->create();
    $wallet = app(CreateMemberWallet::class)->handle($actor, $member, WalletType::MemberSavings, 'Savings', 'SAV-'.$member->membership_number);
    $cash = LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '1100')->firstOrFail();
    $liability = LedgerAccount::query()->whereBelongsTo($organisation)->where('code', '2000')->firstOrFail();

    $posted = [];
    foreach ([['2026-10-01', 1000], ['2026-10-10', 2000], ['2026-10-15', 500]] as [$date, $amount]) {
        $posted[] = app(PostFinancialTransaction::class)->handle(
            $actor,
            new TransactionData(null, FinancialTransactionType::Adjustment, 'GHS', CarbonImmutable::parse($date), 'Statement posting', 'statement-'.$date),
            [
                new TransactionEntryData($cash, EntryType::Debit, $amount),
                new TransactionEntryData($liability, EntryType::Credit, $amount, member: $member, wallet: $wallet),
            ],
        );
    }

    $first = $this->getJson(route('api.v1.wallets.statement', [
        'walletUuid' => $wallet->public_id, 'from' => '2026-10-10', 'to' => '2026-10-15', 'per_page' => 1,
    ]))->assertSuccessful()
        ->assertJsonPath('data.opening_balance_minor', 1000)
        ->assertJsonPath('data.closing_balance_minor', 3500)
        ->assertJsonPath('data.entries.0.running_balance_minor', 3000)
        ->assertJsonPath('data.has_more', true)
        ->assertJsonCount(1, 'data.entries');
    expect($first->json('data.entries.0.transaction_number'))->toBeString();

    $this->getJson(route('api.v1.wallets.statement', [
        'walletUuid' => $wallet->public_id, 'from' => '2026-10-10', 'to' => '2026-10-15', 'per_page' => 1, 'page' => 2,
    ]))->assertSuccessful()
        ->assertJsonPath('data.entries.0.running_balance_minor', 3500)
        ->assertJsonPath('data.has_more', false);

    $this->getJson(route('api.v1.wallets.statement', [
        'walletUuid' => $wallet->public_id, 'from' => '2026-10-16', 'to' => '2026-10-20',
    ]))->assertSuccessful()
        ->assertJsonPath('data.opening_balance_minor', 3500)
        ->assertJsonPath('data.closing_balance_minor', 3500)
        ->assertJsonCount(0, 'data.entries');

    app(ReverseFinancialTransaction::class)->handle($posted[1], $actor, 'Correction', 'statement-reversal');
    $this->getJson(route('api.v1.wallets.statement', [
        'walletUuid' => $wallet->public_id, 'from' => '2026-10-10', 'to' => now()->toDateString(),
    ]))->assertSuccessful()
        ->assertJsonPath('data.closing_balance_minor', 1500)
        ->assertJsonPath('data.entries.0.transaction_status', 'reversed')
        ->assertJsonPath('data.entries.2.running_balance_minor', 1500)
        ->assertJsonCount(3, 'data.entries');
});

test('wallet statements enforce dates, permission, and tenant scope', function () {
    $this->getJson(route('api.v1.wallets.statement', ['walletUuid' => fake()->uuid(), 'from' => '2026-10-01', 'to' => '2026-10-02']))
        ->assertUnauthorized();

    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $actor = User::factory()->create();
    selectStatementApiContext($this, $actor, $organisation, SystemRole::Treasurer);
    $this->getJson(route('api.v1.wallets.statement', ['walletUuid' => fake()->uuid(), 'from' => '2026-10-20', 'to' => '2026-10-01']))
        ->assertUnprocessable();

    $otherMember = Member::factory()->for($otherOrganisation)->create();
    $otherWallet = Wallet::factory()->for($otherOrganisation)->create([
        'walletable_type' => 'member', 'walletable_id' => $otherMember->getKey(),
    ]);
    $this->getJson(route('api.v1.wallets.statement', [
        'walletUuid' => $otherWallet->public_id, 'from' => '2026-10-01', 'to' => '2026-10-20',
    ]))->assertNotFound();

    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $otherBranchWallet = Wallet::factory()->for($organisation)->for($otherBranch)->create();
    $branchActor = User::factory()->create();
    selectStatementApiContext($this, $branchActor, $organisation, SystemRole::Treasurer, $branch);
    $this->getJson(route('api.v1.wallets.statement', [
        'walletUuid' => $otherBranchWallet->public_id, 'from' => '2026-10-01', 'to' => '2026-10-20',
    ]))->assertNotFound();

    $memberActor = User::factory()->create();
    selectStatementApiContext($this, $memberActor, $organisation, SystemRole::Member);
    $this->getJson(route('api.v1.wallets.statement', [
        'walletUuid' => $otherWallet->public_id, 'from' => '2026-10-01', 'to' => '2026-10-20',
    ]))->assertForbidden();
});
