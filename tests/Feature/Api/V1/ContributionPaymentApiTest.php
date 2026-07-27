<?php

use App\Domain\Accounting\Actions\CreateDefaultChartOfAccounts;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Actions\CreateOrganisationWallet;
use App\Domain\Wallets\Enums\WalletType;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Tests\TestCase;

function selectCollectionApiContext(
    TestCase $test,
    User $user,
    Organisation $organisation,
    SystemRole $role,
): void {
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state(['role_id' => Role::findByName($role->value)->getKey()])
        ->create();

    $test->actingAs($user)->postJson(route('api.v1.auth.context.store'), [
        'organisation_uuid' => $organisation->public_id,
        'role_assignment_uuid' => $assignment->public_id,
    ])->assertSuccessful();
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('treasurer records an idempotent unallocated office cash payment and verifies its receipt', function () {
    $treasurer = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'UNITY']);
    selectCollectionApiContext($this, $treasurer, $organisation, SystemRole::Treasurer);
    app(TenantContext::class)->setOrganisation($organisation);
    app(CreateDefaultChartOfAccounts::class)->handle($organisation, $treasurer);
    $source = app(CreateOrganisationWallet::class)->handle(
        $treasurer,
        WalletType::OfficeCash,
        'Office Cash',
        'OFFICE-CASH',
    );
    $member = Member::factory()->active()->for($organisation)->create();
    $payload = [
        'member_uuid' => $member->public_id,
        'amount_minor' => 12500,
        'currency_code' => 'GHS',
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'source_wallet_uuid' => $source->public_id,
        'allocation_strategy' => 'leave_unallocated',
        'payer_name' => 'Community Member',
    ];

    $this->postJson(route('api.v1.contribution-payments.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    $response = $this->withHeader('Idempotency-Key', 'api-payment-1')
        ->postJson(route('api.v1.contribution-payments.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.payment_number', 'UNITY-PAY-'.now()->year.'-000001')
        ->assertJsonPath('data.unallocated_amount_minor', 12500)
        ->assertJsonMissingPath('data.organisation_id')
        ->assertJsonMissingPath('data.financial_transaction_id');

    $this->withHeader('Idempotency-Key', 'api-payment-1')
        ->postJson(route('api.v1.contribution-payments.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.uuid', $response->json('data.uuid'));

    $receipt = $this->getJson(route('api.v1.receipts.show', $response->json('data.receipt.uuid')))
        ->assertSuccessful();
    $this->getJson(route(
        'api.v1.public.receipts.verify',
        $receipt->json('data.verification_code'),
    ))->assertSuccessful()
        ->assertJsonPath('data.valid', true)
        ->assertJsonMissingPath('data.member.email')
        ->assertJsonMissingPath('data.verification_hash');
});

test('member role cannot use staff collection endpoints', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectCollectionApiContext($this, $user, $organisation, SystemRole::Member);

    $this->getJson(route('api.v1.contribution-payments.index'))->assertForbidden();
    $this->postJson(route('api.v1.contribution-payments.store'), [])->assertForbidden();
    $this->getJson(route('api.v1.receipts.index'))->assertForbidden();
});
