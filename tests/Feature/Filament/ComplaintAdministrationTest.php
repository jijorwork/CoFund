<?php

use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\Complaints\ComplaintCaseResource;
use App\Filament\Resources\Complaints\Pages\ListComplaintCases;
use App\Filament\Resources\Complaints\Pages\ViewComplaintCase;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

function establishComplaintFilamentContext(User $actor, Organisation $organisation, ?Branch $branch = null): OrganisationUserRole
{
    $assignment = OrganisationUserRole::factory()->for($actor)->for($organisation)->state([
        'role_id' => Role::findByName(SystemRole::BranchManager->value)->getKey(),
        'branch_id' => $branch?->getKey(),
    ])->create();
    $context = app(TenantContext::class);
    $context->setOrganisation($organisation);
    $context->setBranch($branch);
    $context->setRoleAssignment($assignment->load(['role', 'user']));

    return $assignment;
}

test('complaint panel list and detail enforce tenant branch and public UUID scope', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $foreignOrganisation = Organisation::factory()->create();
    $assignment = establishComplaintFilamentContext($actor, $organisation, $branch);
    $visible = ComplaintCase::factory()->for($organisation)->for($branch)->create(['subject' => 'Visible case']);
    $hiddenBranch = ComplaintCase::factory()->for($organisation)->for($otherBranch)->create(['subject' => 'Other branch case']);
    $hiddenTenant = ComplaintCase::factory()->for($foreignOrganisation)->create(['subject' => 'Foreign case']);
    $this->actingAs($actor);

    Livewire::test(ListComplaintCases::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hiddenBranch, $hiddenTenant]);
    Livewire::test(ViewComplaintCase::class, ['record' => $visible->public_id])
        ->assertSee('Visible case');
    expect(ComplaintCaseResource::getRecordRouteKeyName())->toBe('public_id');

    $session = ['active_context' => [
        'organisation_public_id' => $organisation->public_id,
        'branch_public_id' => $branch->public_id,
        'role_assignment_public_id' => $assignment->public_id,
    ]];
    $this->withSession($session)->get(ComplaintCaseResource::getUrl('view', ['record' => $hiddenTenant->public_id]))->assertNotFound();
    $this->withSession($session)->get(ComplaintCaseResource::getUrl('view', ['record' => $hiddenBranch->public_id]))->assertNotFound();
    $assignment->role->revokePermissionTo(FoundationPermission::ComplaintsView->value);
    expect(ComplaintCaseResource::canViewAny())->toBeFalse();
});

test('complaint intake rejects a member outside the active branch', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $foreignMember = Member::factory()->for($organisation)->for($otherBranch)->create();
    establishComplaintFilamentContext($actor, $organisation, $branch);
    $this->actingAs($actor);

    Livewire::test(ListComplaintCases::class)
        ->callAction(TestAction::make('openCase')->table(), [
            'member_id' => $foreignMember->id,
            'subject' => 'Wrong branch',
            'category' => 'collection',
            'body' => 'This must not be opened.',
        ])
        ->assertHasActionErrors();

    expect(ComplaintCase::query()->count())->toBe(0);
});

test('complaint panel opens a case through the domain action with scoped member and first message', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->for($branch)->create();
    establishComplaintFilamentContext($actor, $organisation, $branch);
    $this->actingAs($actor);

    Livewire::test(ListComplaintCases::class)
        ->assertActionVisible(TestAction::make('openCase')->table())
        ->callAction(TestAction::make('openCase')->table(), [
            'member_id' => $member->id,
            'subject' => 'Collection concern',
            'category' => 'collection',
            'body' => 'Please review my payment.',
        ])
        ->assertHasNoActionErrors();

    $case = ComplaintCase::query()->sole();
    expect($case->organisation_id)->toBe($organisation->id)
        ->and($case->branch_id)->toBe($branch->id)
        ->and($case->member_id)->toBe($member->id)
        ->and($case->messages()->sole()->body)->toBe('Please review my payment.');
});

test('complaint panel delegates correspondence assignment resolution closure and reopening', function () {
    $actor = User::factory()->create();
    $assignee = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    establishComplaintFilamentContext($actor, $organisation, $branch);
    OrganisationUserRole::factory()->for($assignee)->for($organisation)->state([
        'role_id' => Role::findByName(SystemRole::MembershipOfficer->value)->getKey(),
        'branch_id' => $branch->id,
    ])->create();
    $case = ComplaintCase::factory()->for($organisation)->for($branch)->create();
    $this->actingAs($actor);

    Livewire::test(ListComplaintCases::class)
        ->callAction(TestAction::make('respond')->table($case), [
            'visibility' => 'internal',
            'body' => 'The member record is being reviewed.',
        ])
        ->assertHasNoActionErrors();
    Livewire::test(ListComplaintCases::class)
        ->callAction(TestAction::make('assign')->table($case), ['assignee_id' => $assignee->id])
        ->assertHasNoActionErrors();
    Livewire::test(ListComplaintCases::class)
        ->callAction(TestAction::make('resolve')->table($case), ['resolution_summary' => 'The record was corrected.'])
        ->assertHasNoActionErrors();
    Livewire::test(ListComplaintCases::class)
        ->callAction(TestAction::make('close')->table($case))
        ->assertHasNoActionErrors();
    Livewire::test(ListComplaintCases::class)
        ->callAction(TestAction::make('reopen')->table($case))
        ->assertHasNoActionErrors();

    expect($case->refresh()->status->value)->toBe('in_review')
        ->and($case->assigned_to_user_id)->toBe($assignee->id)
        ->and($case->messages()->sole()->body)->toBe('The member record is being reviewed.')
        ->and($case->resolution_summary)->toBeNull();

    Livewire::test(ViewComplaintCase::class, ['record' => $case->public_id])
        ->assertSee('The member record is being reviewed.')
        ->assertActionVisible('respond');
});
