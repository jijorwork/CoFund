<?php

use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\AuditEvents\AuditEventResource;
use App\Filament\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Filament\Resources\AuditEvents\Pages\ViewAuditEvent;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function establishAuditFilamentContext(User $actor, Organisation $organisation, ?Branch $branch = null): OrganisationUserRole
{
    $assignment = OrganisationUserRole::factory()->for($actor)->for($organisation)->state([
        'role_id' => Role::findByName(SystemRole::Auditor->value)->getKey(),
        'branch_id' => $branch?->getKey(),
    ])->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    return $assignment;
}

function createAuditPanelEvent(Organisation $organisation, OrganisationUserRole $assignment, ?Branch $branch, string $eventKey): AuditEvent
{
    return AuditEvent::query()->create([
        'organisation_id' => $organisation->id,
        'branch_id' => $branch?->id,
        'actor_user_id' => $assignment->user_id,
        'acting_role_id' => $assignment->role_id,
        'role_assignment_id' => $assignment->id,
        'event_key' => $eventKey,
        'subject_type' => 'complaint_case',
        'subject_public_id' => (string) Str::uuid(),
        'details' => ['reason' => 'Reviewed'],
    ]);
}

test('audit panel is read only and limits rows and direct URLs by organisation and branch', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = establishAuditFilamentContext($actor, $organisation, $branch);
    $foreignAssignment = OrganisationUserRole::factory()->for($actor)->for($otherOrganisation)->state([
        'role_id' => Role::findByName(SystemRole::Auditor->value)->getKey(),
    ])->create();
    $visible = createAuditPanelEvent($organisation, $assignment, $branch, 'complaint.opened');
    $hiddenBranch = createAuditPanelEvent($organisation, $assignment, $otherBranch, 'complaint.assigned');
    $hiddenOrganisation = createAuditPanelEvent($otherOrganisation, $foreignAssignment, null, 'member.status_changed');
    $this->actingAs($actor);

    Livewire::test(ListAuditEvents::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hiddenBranch, $hiddenOrganisation]);
    Livewire::test(ViewAuditEvent::class, ['record' => $visible->public_id])
        ->assertSee('complaint.opened')
        ->assertSee('Reviewed');

    $session = ['active_context' => [
        'organisation_public_id' => $organisation->public_id,
        'branch_public_id' => $branch->public_id,
        'role_assignment_public_id' => $assignment->public_id,
    ]];
    $this->withSession($session)->get(AuditEventResource::getUrl('view', ['record' => $hiddenBranch->public_id]))->assertNotFound();
    $this->withSession($session)->get(AuditEventResource::getUrl('view', ['record' => $hiddenOrganisation->public_id]))->assertNotFound();

    expect(AuditEventResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(AuditEventResource::canCreate())->toBeFalse()
        ->and(AuditEventResource::canEdit($visible))->toBeFalse()
        ->and(AuditEventResource::canDelete($visible))->toBeFalse();
});

test('audit panel requires both panel and audit-report permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $assignment = establishAuditFilamentContext($actor, $organisation);
    $this->actingAs($actor);

    expect(AuditEventResource::canViewAny())->toBeTrue();
    $assignment->role->revokePermissionTo(FoundationPermission::FilamentAuditView->value);
    expect(AuditEventResource::canViewAny())->toBeFalse();
});
