<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Models\SavedReport;
use App\Filament\Resources\SavedReports\Pages\ListSavedReports;
use App\Filament\Resources\SavedReports\SavedReportResource;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function establishSavedReportContext(User $user, Organisation $organisation, SystemRole $role, ?Branch $branch = null): OrganisationUserRole
{
    $assignment = OrganisationUserRole::factory()
        ->for($user)
        ->for($organisation)
        ->state([
            'branch_id' => $branch?->getKey(),
            'role_id' => Role::findByName($role->value)->getKey(),
        ])
        ->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    return $assignment;
}

test('saved report library enforces tenant, visibility, role, branch, and report permissions', function () {
    $actor = User::factory()->create();
    $otherUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = establishSavedReportContext($actor, $organisation, SystemRole::MembershipOfficer, $branch);
    $this->actingAs($actor);

    $own = SavedReport::factory()->for($organisation)->for($actor, 'owner')->create([
        'report_key' => 'groups',
        'name' => 'My groups',
    ]);
    $organisationVisible = SavedReport::factory()->for($organisation)->for($otherUser, 'owner')->organisationVisible()->create([
        'report_key' => 'groups',
        'name' => 'Shared groups',
    ]);
    $roleVisible = SavedReport::factory()->for($organisation)->for($otherUser, 'owner')->roleVisible()->create([
        'report_key' => 'groups',
        'acting_role_id' => $assignment->role_id,
        'name' => 'Role groups',
    ]);
    $private = SavedReport::factory()->for($organisation)->for($otherUser, 'owner')->create(['report_key' => 'groups']);
    $financial = SavedReport::factory()->for($organisation)->for($actor, 'owner')->create(['report_key' => 'trial-balance']);
    $otherBranchReport = SavedReport::factory()->for($organisation)->for($actor, 'owner')->create([
        'report_key' => 'groups',
        'filters' => ['branch_uuid' => $otherBranch->public_id],
    ]);
    $otherTenant = SavedReport::factory()->for($otherOrganisation)->for($actor, 'owner')->create(['report_key' => 'groups']);

    expect(SavedReportResource::getEloquentQuery()->pluck('public_id')->sort()->values()->all())
        ->toBe(collect([$own, $organisationVisible, $roleVisible])->pluck('public_id')->sort()->values()->all());
    expect(SavedReportResource::canView($own))->toBeTrue()
        ->and(SavedReportResource::canView($roleVisible))->toBeTrue()
        ->and(SavedReportResource::canView($private))->toBeFalse()
        ->and(SavedReportResource::canView($financial))->toBeFalse()
        ->and(SavedReportResource::canView($otherBranchReport))->toBeFalse()
        ->and(SavedReportResource::canView($otherTenant))->toBeFalse();
});

test('saved report direct links use scoped public identifiers', function () {
    $actor = User::factory()->create();
    $otherUser = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishSavedReportContext($actor, $organisation, SystemRole::Treasurer);
    $visible = SavedReport::factory()->for($organisation)->for($actor, 'owner')->create();
    $hidden = SavedReport::factory()->for($organisation)->for($otherUser, 'owner')->create();

    $this->actingAs($actor);
    $this->get(SavedReportResource::getUrl('view', ['record' => $visible->public_id]))->assertSuccessful();
    $this->get(SavedReportResource::getUrl('view', ['record' => $hidden->public_id]))->assertNotFound();
});

test('authorised staff can save a scoped report through the panel', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    establishSavedReportContext($actor, $organisation, SystemRole::OrganisationAdmin, $branch);
    $this->actingAs($actor);

    Livewire::test(ListSavedReports::class)
        ->assertActionVisible(TestAction::make('createReport'))
        ->callAction(TestAction::make('createReport'), data: [
            'name' => 'Branch trial balance',
            'description' => 'Monthly branch accounts',
            'report_key' => 'trial-balance',
            'visibility' => 'private',
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-22',
        ])
        ->assertHasNoActionErrors();

    $report = SavedReport::query()->sole();
    expect($report->owner_user_id)->toBe($actor->id)
        ->and($report->organisation_id)->toBe($organisation->id)
        ->and($report->filters['branch_uuid'])->toBe($branch->public_id)
        ->and($report->filters['date_from'])->toBe('2026-10-01');
});

test('a staff member cannot save a report outside their permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishSavedReportContext($actor, $organisation, SystemRole::MembershipOfficer);
    $this->actingAs($actor);

    Livewire::test(ListSavedReports::class)
        ->assertActionVisible(TestAction::make('createReport'))
        ->callAction(TestAction::make('createReport'), data: [
            'name' => 'Restricted financial report',
            'report_key' => 'trial-balance',
            'visibility' => 'private',
        ])
        ->assertHasActionErrors();
    expect(SavedReport::query()->count())->toBe(0);
});

test('saved report authoring rejects future dates and unauthorised sharing', function () {
    $this->travelTo('2026-10-22 12:00:00');
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishSavedReportContext($actor, $organisation, SystemRole::MembershipOfficer);
    $this->actingAs($actor);

    Livewire::test(ListSavedReports::class)
        ->callAction(TestAction::make('createReport'), data: [
            'name' => 'Future groups',
            'report_key' => 'groups',
            'visibility' => 'private',
            'date_to' => '2026-10-23',
        ])
        ->assertHasActionErrors();

    Livewire::test(ListSavedReports::class)
        ->callAction(TestAction::make('createReport'), data: [
            'name' => 'Shared groups',
            'report_key' => 'groups',
            'visibility' => 'organisation',
        ])
        ->assertHasActionErrors();
    expect(SavedReport::query()->count())->toBe(0);
});
