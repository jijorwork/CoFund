<?php

use App\Domain\Contributions\Actions\EnrolMemberInContributionPlan;
use App\Domain\Contributions\Actions\SuspendContributionPlanEnrolment;
use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Enums\ObligationSatisfactionStatus;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\ContributionEnrolments\ContributionEnrolmentResource;
use App\Filament\Resources\ContributionEnrolments\Pages\ListContributionEnrolments;
use App\Filament\Resources\ContributionEnrolments\Pages\ViewContributionEnrolment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function establishContributionEnrolmentFilamentContext(
    User $user,
    Organisation $organisation,
    SystemRole $role = SystemRole::OrganisationAdmin,
    ?Branch $branch = null,
): OrganisationUserRole {
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

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
    CarbonImmutable::setTestNow('2026-07-27 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('contribution enrolments are organisation and exact branch scoped without n plus one relations', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    establishContributionEnrolmentFilamentContext(
        $actor,
        $organisation,
        branch: $activeBranch,
    );

    $plan = ContributionPlan::factory()->for($organisation)->create();
    $visible = MemberPlanEnrolment::factory()
        ->for($plan, 'contributionPlan')
        ->create([
            'member_id' => Member::factory()->for($organisation)->for($activeBranch)->create(),
            'branch_id' => $activeBranch->id,
        ]);
    $otherBranchEnrolment = MemberPlanEnrolment::factory()
        ->for($plan, 'contributionPlan')
        ->create([
            'member_id' => Member::factory()->for($organisation)->for($otherBranch)->create(),
            'branch_id' => $otherBranch->id,
        ]);
    $organisationWideEnrolment = MemberPlanEnrolment::factory()
        ->for($plan, 'contributionPlan')
        ->create([
            'member_id' => Member::factory()->for($organisation)->create(),
            'branch_id' => null,
        ]);
    $foreign = MemberPlanEnrolment::factory()
        ->for(ContributionPlan::factory()->for($foreignOrganisation), 'contributionPlan')
        ->create();

    $this->actingAs($actor);

    Livewire::test(ListContributionEnrolments::class)
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([
            $otherBranchEnrolment,
            $organisationWideEnrolment,
            $foreign,
        ]);

    $loaded = ContributionEnrolmentResource::getEloquentQuery()
        ->findOrFail($visible->id);

    expect($loaded)
        ->relationLoaded('contributionPlan')->toBeTrue()
        ->relationLoaded('member')->toBeTrue()
        ->relationLoaded('branch')->toBeTrue()
        ->relationLoaded('group')->toBeTrue()
        ->relationLoaded('createdBy')->toBeTrue()
        ->relationLoaded('approvedBy')->toBeTrue()
        ->and($loaded->obligations_count)->toBeInt()
        ->and($loaded->payments_count)->toBeInt();
});

test('contribution enrolment urls and navigation reject foreign scope and missing permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = establishContributionEnrolmentFilamentContext(
        $actor,
        $organisation,
        branch: $activeBranch,
    );
    $plan = ContributionPlan::factory()->for($organisation)->create();
    $otherBranchEnrolment = MemberPlanEnrolment::factory()
        ->for($plan, 'contributionPlan')
        ->create([
            'member_id' => Member::factory()->for($organisation)->for($otherBranch)->create(),
            'branch_id' => $otherBranch->id,
        ]);
    $foreign = MemberPlanEnrolment::factory()
        ->for(ContributionPlan::factory()->for($foreignOrganisation), 'contributionPlan')
        ->create();
    $session = [
        'active_context' => [
            'organisation_public_id' => $organisation->public_id,
            'branch_public_id' => $activeBranch->public_id,
            'role_assignment_public_id' => $assignment->public_id,
        ],
    ];

    $this->actingAs($actor)
        ->withSession($session)
        ->get(ContributionEnrolmentResource::getUrl('view', [
            'record' => $foreign->public_id,
        ]))
        ->assertNotFound();

    $this->withSession($session)
        ->get(ContributionEnrolmentResource::getUrl('view', [
            'record' => $otherBranchEnrolment->public_id,
        ]))
        ->assertNotFound();

    $assignment->role->revokePermissionTo(
        FoundationPermission::ContributionEnrolmentsView->value,
    );

    expect(ContributionEnrolmentResource::canViewAny())->toBeFalse();

    $this->withSession($session)
        ->get(ContributionEnrolmentResource::getUrl())
        ->assertForbidden();
});

test('enrolment listing is responsive searchable and uses public uuids', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionEnrolmentFilamentContext($actor, $organisation);
    $member = Member::factory()->for($organisation)->create([
        'membership_number' => 'MEM-ENROL-001',
    ]);
    $plan = ContributionPlan::factory()->for($organisation)->create([
        'name' => 'Builders Fund',
        'code' => 'BUILDERS',
    ]);
    $enrolment = MemberPlanEnrolment::factory()
        ->for($plan, 'contributionPlan')
        ->for($member)
        ->create();

    $this->actingAs($actor);

    $component = Livewire::test(ListContributionEnrolments::class)
        ->searchTable('MEM-ENROL-001')
        ->assertCanSeeTableRecords([$enrolment]);

    expect($component->instance()->getTable()->getColumn('start_date')?->getVisibleFrom())
        ->toBe('md')
        ->and($component->instance()->getTable()->getColumn('selected_amount_minor')?->getVisibleFrom())
        ->toBe('lg')
        ->and($component->instance()->getTable()->getColumn('obligations_count')?->getVisibleFrom())
        ->toBe('xl')
        ->and(ContributionEnrolmentResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(ContributionEnrolmentResource::getGlobalSearchResultUrl($enrolment))
        ->toContain($enrolment->public_id)
        ->and(ContributionEnrolmentResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit']);
});

test('member enrolment delegates only verified scoped values to the domain action', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $branch = Branch::factory()->for($organisation)->create();
    establishContributionEnrolmentFilamentContext(
        $actor,
        $organisation,
        branch: $branch,
    );
    $member = Member::factory()->active()->for($organisation)->for($branch)->create();
    $plan = ContributionPlan::factory()
        ->active()
        ->memberSelectedAmount()
        ->for($organisation)
        ->for($branch)
        ->create();
    $result = MemberPlanEnrolment::factory()
        ->for($plan, 'contributionPlan')
        ->for($member)
        ->make();

    $enrol = Mockery::mock(EnrolMemberInContributionPlan::class);
    $enrol->shouldReceive('handle')
        ->once()
        ->withArgs(fn (
            User $selectedActor,
            Member $selectedMember,
            ContributionPlan $selectedPlan,
            ?int $amount,
            CarbonImmutable $startDate,
            ?CarbonImmutable $endDate,
        ): bool => $selectedActor->is($actor)
            && $selectedMember->is($member)
            && $selectedPlan->is($plan)
            && $amount === 15000
            && $startDate->toDateString() === '2026-08-01'
            && $endDate?->toDateString() === '2026-12-31')
        ->andReturn($result);
    app()->instance(EnrolMemberInContributionPlan::class, $enrol);

    $this->actingAs($actor);

    Livewire::test(ListContributionEnrolments::class)
        ->assertActionVisible(TestAction::make('enrolMember')->table())
        ->callAction(TestAction::make('enrolMember')->table(), data: [
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'selected_amount_minor' => 15000,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
            'organisation_id' => Organisation::factory()->create()->id,
            'branch_id' => Branch::factory()->for($organisation)->create()->id,
            'status' => EnrolmentStatus::Active->value,
            'approval_status' => ApprovalStatus::Approved->value,
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Member enrolled');
});

test('member enrolment rejects foreign member and plan tampering before domain delegation', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    establishContributionEnrolmentFilamentContext($actor, $organisation);
    $member = Member::factory()->active()->for($organisation)->create();
    $foreignMember = Member::factory()->active()->for($foreignOrganisation)->create();
    $plan = ContributionPlan::factory()->active()->for($organisation)->create();
    $foreignPlan = ContributionPlan::factory()->active()->for($foreignOrganisation)->create();

    $enrol = Mockery::mock(EnrolMemberInContributionPlan::class);
    $enrol->shouldNotReceive('handle');
    app()->instance(EnrolMemberInContributionPlan::class, $enrol);

    $this->actingAs($actor);

    Livewire::test(ListContributionEnrolments::class)
        ->callAction(TestAction::make('enrolMember')->table(), data: [
            'member_id' => $foreignMember->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-08-01',
        ])
        ->assertActionHalted();

    Livewire::test(ListContributionEnrolments::class)
        ->callAction(TestAction::make('enrolMember')->table(), data: [
            'member_id' => $member->id,
            'plan_id' => $foreignPlan->id,
            'start_date' => '2026-08-01',
        ])
        ->assertActionHalted();
});

test('lifecycle actions delegate to domain services and respect role permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionEnrolmentFilamentContext($actor, $organisation);
    $enrolment = MemberPlanEnrolment::factory()
        ->active()
        ->for(ContributionPlan::factory()->for($organisation), 'contributionPlan')
        ->create();

    $suspend = Mockery::mock(SuspendContributionPlanEnrolment::class);
    $suspend->shouldReceive('handle')
        ->once()
        ->withArgs(fn (
            MemberPlanEnrolment $selected,
            User $selectedActor,
            string $reason,
        ): bool => $selected->is($enrolment)
            && $selectedActor->is($actor)
            && $reason === 'Member requested a temporary pause.')
        ->andReturn($enrolment);
    app()->instance(SuspendContributionPlanEnrolment::class, $suspend);

    $this->actingAs($actor);

    Livewire::test(ListContributionEnrolments::class)
        ->callAction(TestAction::make('suspend')->table($enrolment), data: [
            'reason' => ' Member requested a temporary pause. ',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified('Enrolment suspended');

    $auditor = User::factory()->create();
    establishContributionEnrolmentFilamentContext(
        $auditor,
        $organisation,
        SystemRole::Auditor,
    );
    $this->actingAs($auditor);

    Livewire::test(ListContributionEnrolments::class)
        ->assertActionHidden(TestAction::make('enrolMember')->table())
        ->assertActionHidden(TestAction::make('suspend')->table($enrolment));
});

test('enrolment detail shows a read only schedule and calculated contribution position', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['timezone' => 'Africa/Accra']);
    establishContributionEnrolmentFilamentContext($actor, $organisation);
    $enrolment = MemberPlanEnrolment::factory()
        ->active()
        ->for(ContributionPlan::factory()->for($organisation), 'contributionPlan')
        ->create();
    ContributionObligation::factory()->overdue()->for($enrolment, 'enrolment')->create([
        'sequence_number' => 1,
        'expected_amount_minor' => 15000,
        'satisfied_amount_minor' => 5000,
        'outstanding_amount_minor' => 10000,
        'satisfaction_status' => ObligationSatisfactionStatus::PartiallySatisfied,
    ]);
    ContributionObligation::factory()->upcoming()->for($enrolment, 'enrolment')->create([
        'sequence_number' => 2,
        'expected_amount_minor' => 20000,
        'outstanding_amount_minor' => 20000,
        'schedule_status' => ObligationStatus::Upcoming,
    ]);

    $this->actingAs($actor);

    $component = Livewire::test(ViewContributionEnrolment::class, [
        'record' => $enrolment->public_id,
    ])
        ->assertSee('Contribution position')
        ->assertSee('In Arrears')
        ->assertSee('GHS 150.00')
        ->assertSee('GHS 50.00')
        ->assertSee('GHS 100.00')
        ->assertSee('Schedule');

    $record = $component->instance()->getRecord();

    expect($record)
        ->toBeInstanceOf(MemberPlanEnrolment::class)
        ->relationLoaded('obligations')->toBeTrue()
        ->and($record->obligations)->toHaveCount(2)
        ->and($record->getAttribute('position_status'))->toBe('in_arrears')
        ->and($record->getAttribute('position_overdue_count'))->toBe(1)
        ->and($record->getAttribute('position_upcoming_count'))->toBe(1);
});
