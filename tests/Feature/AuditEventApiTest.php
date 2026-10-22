<?php

use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function selectAuditApiContext(TestCase $test, User $user, Organisation $organisation, SystemRole $role, ?Branch $branch = null): OrganisationUserRole
{
    $assignment = OrganisationUserRole::factory()->for($user)->for($organisation)->state([
        'role_id' => Role::findByName($role->value)->getKey(),
        'branch_id' => $branch?->getKey(),
    ])->create();

    $test->actingAs($user)->postJson(route('api.v1.auth.context.store'), [
        'organisation_uuid' => $organisation->public_id,
        'branch_uuid' => $branch?->public_id,
        'role_assignment_uuid' => $assignment->public_id,
    ])->assertSuccessful();

    return $assignment;
}

function createAuditApiEvent(Organisation $organisation, OrganisationUserRole $assignment, ?Branch $branch = null, string $eventKey = 'member.status_changed'): AuditEvent
{
    return AuditEvent::query()->create([
        'organisation_id' => $organisation->getKey(),
        'branch_id' => $branch?->getKey(),
        'actor_user_id' => $assignment->user_id,
        'acting_role_id' => $assignment->role_id,
        'role_assignment_id' => $assignment->getKey(),
        'event_key' => $eventKey,
        'subject_type' => 'member',
        'subject_public_id' => (string) Str::uuid(),
        'details' => ['reason' => 'Review'],
    ]);
}

test('audit endpoints require authentication and audit permission', function () {
    $this->getJson(route('api.v1.audit-events.index'))->assertUnauthorized();

    $organisation = Organisation::factory()->create();
    $collector = User::factory()->create();
    selectAuditApiContext($this, $collector, $organisation, SystemRole::Collector);

    $this->getJson(route('api.v1.audit-events.index'))->assertForbidden();
});

test('auditors see only their organisation audit events and may filter them', function () {
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $auditor = User::factory()->create();
    $assignment = selectAuditApiContext($this, $auditor, $organisation, SystemRole::Auditor);
    $visible = createAuditApiEvent($organisation, $assignment);
    createAuditApiEvent($organisation, $assignment, eventKey: 'approval.decided');
    $foreignActor = User::factory()->create();
    $foreignAssignment = OrganisationUserRole::factory()->for($foreignActor)->for($otherOrganisation)->state(['role_id' => $assignment->role_id])->create();
    $foreign = createAuditApiEvent($otherOrganisation, $foreignAssignment);

    $this->getJson(route('api.v1.audit-events.index', ['event_key' => 'member.status_changed']))
        ->assertSuccessful()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $visible->public_id)
        ->assertJsonPath('data.0.actor_uuid', $auditor->public_id)
        ->assertJsonMissingPath('data.0.actor_user_id');
    $this->getJson(route('api.v1.audit-events.show', $visible->public_id))->assertSuccessful();
    $this->getJson(route('api.v1.audit-events.show', $foreign->public_id))->assertNotFound();
});

test('branch assigned auditors cannot access another branch or organisation wide events', function () {
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $auditor = User::factory()->create();
    $assignment = selectAuditApiContext($this, $auditor, $organisation, SystemRole::Auditor, $branch);
    $visible = createAuditApiEvent($organisation, $assignment, $branch);
    $hidden = createAuditApiEvent($organisation, $assignment, $otherBranch);
    $organisationWide = createAuditApiEvent($organisation, $assignment);

    $this->getJson(route('api.v1.audit-events.index'))->assertSuccessful()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $visible->public_id);
    $this->getJson(route('api.v1.audit-events.show', $hidden->public_id))->assertNotFound();
    $this->getJson(route('api.v1.audit-events.show', $organisationWide->public_id))->assertNotFound();
});
