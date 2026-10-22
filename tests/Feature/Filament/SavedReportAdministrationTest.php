<?php

use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Reports\Models\SavedReport;
use App\Filament\Resources\SavedReports\SavedReportResource;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;

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
