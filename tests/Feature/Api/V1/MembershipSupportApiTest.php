<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Models\OrganisationUser;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('group APIs preserve historical membership and reject duplicates', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);
    $member = Member::factory()->for($organisation)->create();

    $groupResponse = $this->postJson(route('api.v1.groups.store'), [
        'name' => 'Unity Circle',
        'code' => 'UNITY',
        'group_type' => 'susu_circle',
    ])->assertCreated();
    $groupUuid = $groupResponse->json('data.uuid');

    $this->postJson(route('api.v1.groups.members.add', $groupUuid), [
        'member_uuid' => $member->public_id,
    ])->assertNoContent();
    $this->postJson(route('api.v1.groups.members.add', $groupUuid), [
        'member_uuid' => $member->public_id,
    ])->assertUnprocessable();
    $this->postJson(route('api.v1.groups.members.remove', [$groupUuid, $member->public_id]))
        ->assertNoContent();

    expect(Group::where('public_id', $groupUuid)->first()->memberships()->count())->toBe(1);
});

test('contacts enforce beneficiary allocation and mask national identifiers', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);
    $member = Member::factory()->for($organisation)->create();

    $this->postJson(route('api.v1.members.contacts.store', $member->public_id), [
        'contact_type' => 'beneficiary',
        'full_name' => 'Akua Mensah',
        'relationship' => 'Child',
        'phone' => '0240000000',
        'national_id_type' => 'ghana_card',
        'national_id_number' => 'GHA-123456789-0',
        'percentage_entitlement' => '75.00',
    ])->assertCreated()
        ->assertJsonPath('meta.remaining_beneficiary_percentage', '25.00')
        ->assertJsonMissing(['national_id_number' => 'GHA-123456789-0']);

    $this->postJson(route('api.v1.members.contacts.store', $member->public_id), [
        'contact_type' => 'beneficiary',
        'full_name' => 'Yaw Mensah',
        'relationship' => 'Child',
        'phone' => '0240000001',
        'percentage_entitlement' => '25.01',
    ])->assertUnprocessable();
});

test('nested contact routes never resolve a contact from another member', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);
    $member = Member::factory()->for($organisation)->create();
    $other = Member::factory()->for($organisation)->create();
    $contact = MemberContact::factory()->for($other)->create();

    $this->getJson(route('api.v1.members.contacts.show', [
        $member->public_id,
        $contact->public_id,
    ]))->assertNotFound();
});

test('private document upload download and review never expose storage paths', function () {
    Storage::fake('local');
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);
    $member = Member::factory()->for($organisation)->create();

    $response = $this->post(route('api.v1.members.documents.store', $member->public_id), [
        'document_type' => 'national_id',
        'file' => UploadedFile::fake()->create('identity.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonMissingPath('data.path')
        ->assertJsonMissingPath('data.disk');

    $uuid = $response->json('data.uuid');
    $document = MemberDocument::where('public_id', $uuid)->firstOrFail();
    Storage::disk('local')->assertExists($document->path);

    $this->get(route('api.v1.members.documents.download', [$member->public_id, $uuid]))
        ->assertSuccessful();
    $this->postJson(route('api.v1.members.documents.verify', [$member->public_id, $uuid]))
        ->assertSuccessful()
        ->assertJsonPath('data.verification_status', 'verified');
});

test('linking a user requires access and unlinking preserves both records', function () {
    $administrator = User::factory()->create();
    $target = User::factory()->create();
    $organisation = Organisation::factory()->create();
    selectMembershipApiContext($this, $administrator, $organisation, SystemRole::OrganisationAdmin);
    $member = Member::factory()->for($organisation)->create();

    $this->postJson(route('api.v1.members.link-user', $member->public_id), [
        'user_uuid' => $target->public_id,
    ])->assertUnprocessable();

    OrganisationUser::factory()
        ->for($target)->for($organisation)->create();
    $this->postJson(route('api.v1.members.link-user', $member->public_id), [
        'user_uuid' => $target->public_id,
    ])->assertSuccessful()->assertJsonPath('data.linked_user', true);
    $this->postJson(route('api.v1.members.unlink-user', $member->public_id))
        ->assertSuccessful()->assertJsonPath('data.linked_user', false);

    expect($target->fresh())->not->toBeNull()
        ->and($member->fresh())->not->toBeNull();
});
