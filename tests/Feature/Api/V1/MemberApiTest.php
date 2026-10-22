<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('organisation administrator creates and updates a member without exposing internal ids', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->create(['code' => 'UNITY']);
    $branch = Branch::factory()->for($organisation)->create();
    selectMembershipApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);

    $response = $this->postJson(route('api.v1.members.store'), [
        'branch_uuid' => $branch->public_id,
        'first_name' => 'Abena',
        'last_name' => 'Owusu',
        'phone' => '024 111 2222',
        'status' => 'pending',
    ])->assertCreated()
        ->assertJsonPath('data.membership_number', 'UNITY-2026-000001')
        ->assertJsonPath('data.phone', '+233241112222')
        ->assertJsonMissing(['organisation_id' => $organisation->id]);

    $uuid = $response->json('data.uuid');

    $this->patchJson(route('api.v1.members.update', $uuid), [
        'preferred_name' => 'Abena O.',
        'status' => 'deceased',
        'membership_number' => 'ATTACK',
    ])->assertUnprocessable();

    expect(Member::where('public_id', $uuid)->value('membership_number'))
        ->toBe('UNITY-2026-000001');
});

test('member list is paginated searchable and tenant isolated', function () {
    $auditor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $auditor, $organisation, SystemRole::Auditor);
    $visible = Member::factory()->for($organisation)->create([
        'first_name' => 'Distinctive',
        'phone' => '+233241234567',
        'membership_number' => 'ORG-2026-000001',
    ]);
    $hidden = Member::factory()->for($otherOrganisation)->create(['first_name' => 'Distinctive']);

    $this->getJson(route('api.v1.members.index', [
        'filter' => ['name' => 'Distinctive'],
    ]))->assertSuccessful()
        ->assertJsonFragment(['uuid' => $visible->public_id])
        ->assertJsonMissing(['uuid' => $hidden->public_id])
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'request_id']]);

    $this->getJson(route('api.v1.members.index', [
        'filter' => ['phone' => '024 123 4567'],
    ]))->assertJsonFragment(['uuid' => $visible->public_id]);
});

test('branch manager sees only matching branch members and no organisation wide records', function () {
    $manager = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    selectMembershipApiContext($this, $manager, $organisation, SystemRole::BranchManager, $branch);
    $visible = Member::factory()->for($organisation)->create(['branch_id' => $branch->id]);
    $hidden = Member::factory()->for($organisation)->create(['branch_id' => $otherBranch->id]);
    $branchless = Member::factory()->for($organisation)->create(['branch_id' => null]);

    $this->getJson(route('api.v1.members.index'))
        ->assertSuccessful()
        ->assertJsonFragment(['uuid' => $visible->public_id])
        ->assertJsonMissing(['uuid' => $hidden->public_id])
        ->assertJsonMissing(['uuid' => $branchless->public_id]);

    $this->getJson(route('api.v1.members.show', $hidden->public_id))->assertNotFound();
});

test('member role cannot use staff listing while collector may list without document access', function () {
    $organisation = Organisation::factory()->create();
    $memberUser = User::factory()->create();
    selectMembershipApiContext($this, $memberUser, $organisation, SystemRole::Member);
    $this->getJson(route('api.v1.members.index'))->assertForbidden();

    $collector = User::factory()->create();
    selectMembershipApiContext($this, $collector, $organisation, SystemRole::Collector);
    $member = Member::factory()->for($organisation)->create();
    $this->getJson(route('api.v1.members.index'))->assertSuccessful();
    $this->getJson(route('api.v1.members.documents.index', $member->public_id))->assertForbidden();
});

test('lifecycle endpoints create history and enforce permissions', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);
    $member = Member::factory()->pending()->for($organisation)->create();

    $this->postJson(route('api.v1.members.activate', $member->public_id))->assertSuccessful();
    $this->postJson(route('api.v1.members.suspend', $member->public_id), [
        'reason' => 'Policy review',
    ])->assertSuccessful()->assertJsonPath('data.status', 'suspended');

    expect($member->statusHistories()->count())->toBe(2);
});

test('invalid member filters and inaccessible uuids use safe errors', function () {
    $auditor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $auditor, $organisation, SystemRole::Auditor);
    $foreign = Member::factory()->create();

    $this->getJson(route('api.v1.members.index', ['sort' => 'notes']))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');
    $this->getJson(route('api.v1.members.show', $foreign->public_id))->assertNotFound();
});
