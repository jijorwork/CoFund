<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Tests\TestCase;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function selectComplaintApiContext(TestCase $test, User $actor, Organisation $organisation, SystemRole $role, ?Branch $branch = null): void
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
}

test('staff complaint API supports intake, correspondence, assignment and resolution', function () {
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->for($branch)->create();
    $manager = User::factory()->create();
    selectComplaintApiContext($this, $manager, $organisation, SystemRole::BranchManager, $branch);

    $created = $this->postJson(route('api.v1.complaints.store'), [
        'member_uuid' => $member->public_id,
        'subject' => 'Collection concern',
        'category' => 'collection',
        'body' => 'Please review my payment record.',
    ])->assertCreated()->assertJsonPath('data.status', 'open')->assertJsonPath('data.member_uuid', $member->public_id)
        ->assertJsonMissingPath('data.member_id');
    $uuid = $created->json('data.uuid');

    $assignee = User::factory()->create();
    OrganisationUserRole::factory()->for($assignee)->for($organisation)->state([
        'role_id' => Role::findByName(SystemRole::MembershipOfficer->value)->getKey(),
        'branch_id' => $branch->id,
    ])->create();
    $this->postJson(route('api.v1.complaints.assign', $uuid), ['assignee_uuid' => $assignee->public_id])
        ->assertSuccessful()->assertJsonPath('data.status', 'in_review');
    $this->postJson(route('api.v1.complaints.messages.store', $uuid), [
        'visibility' => 'internal',
        'body' => 'Checking the ledger entry.',
    ])->assertSuccessful()->assertJsonPath('data.visibility', 'internal');
    $this->postJson(route('api.v1.complaints.resolve', $uuid), ['resolution_summary' => 'Corrected ledger entry.'])
        ->assertSuccessful()->assertJsonPath('data.status', 'resolved');
    $this->postJson(route('api.v1.complaints.close', $uuid))->assertSuccessful()->assertJsonPath('data.status', 'closed');
    $this->getJson(route('api.v1.complaints.show', $uuid))->assertSuccessful()->assertJsonCount(2, 'data.messages');
    $this->postJson(route('api.v1.complaints.reopen', $uuid))->assertSuccessful()->assertJsonPath('data.status', 'in_review');
});

test('complaint API protects permission, tenant and branch boundaries', function () {
    $this->getJson(route('api.v1.complaints.index'))->assertUnauthorized();

    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->for($branch)->create();
    $manager = User::factory()->create();
    selectComplaintApiContext($this, $manager, $organisation, SystemRole::BranchManager, $branch);
    $created = $this->postJson(route('api.v1.complaints.store'), [
        'member_uuid' => $member->public_id,
        'subject' => 'Branch complaint',
        'category' => 'service',
        'body' => 'Service issue details.',
    ])->assertCreated();
    $uuid = $created->json('data.uuid');

    $this->postJson(route('api.v1.complaints.store'), [
        'member_uuid' => Member::factory()->for($otherOrganisation)->create()->public_id,
        'subject' => 'Foreign member',
        'category' => 'service',
        'body' => 'Service issue details.',
    ])->assertNotFound();
    $this->postJson(route('api.v1.complaints.store'), [
        'member_uuid' => Member::factory()->for($organisation)->for($otherBranch)->create()->public_id,
        'subject' => 'Other branch',
        'category' => 'service',
        'body' => 'Service issue details.',
    ])->assertUnprocessable()->assertJsonPath('error.code', 'complaint_operation_invalid');

    $auditor = User::factory()->create();
    selectComplaintApiContext($this, $auditor, $organisation, SystemRole::Auditor, $branch);
    $this->getJson(route('api.v1.complaints.show', $uuid))->assertSuccessful();
    $this->postJson(route('api.v1.complaints.resolve', $uuid), ['resolution_summary' => 'Should fail'])
        ->assertForbidden();

    $otherBranchManager = User::factory()->create();
    selectComplaintApiContext($this, $otherBranchManager, $organisation, SystemRole::BranchManager, $otherBranch);
    $this->getJson(route('api.v1.complaints.show', $uuid))->assertNotFound();
    $this->getJson(route('api.v1.complaints.index'))->assertSuccessful()->assertJsonCount(0, 'data');

    $foreignManager = User::factory()->create();
    selectComplaintApiContext($this, $foreignManager, $otherOrganisation, SystemRole::BranchManager);
    $this->getJson(route('api.v1.complaints.show', $uuid))->assertNotFound();
});
