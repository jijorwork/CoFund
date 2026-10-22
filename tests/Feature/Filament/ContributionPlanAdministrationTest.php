<?php

use App\Domain\Contributions\Actions\CreateContributionPlan;
use App\Domain\Contributions\Actions\UpdateContributionPlan;
use App\Domain\Contributions\Enums\ContributionAmountType;
use App\Domain\Contributions\Enums\ContributionFrequency;
use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Enums\ContributionPlanType;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Group;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Filament\Resources\ContributionPlans\ContributionPlanResource;
use App\Filament\Resources\ContributionPlans\Pages\ListContributionPlans;
use App\Filament\Resources\ContributionPlans\Pages\ViewContributionPlan;
use App\Models\User;
use Database\Seeders\SystemAuthorizationSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function establishContributionPlanFilamentContext(
    User $user,
    Organisation $organisation,
    SystemRole $role = SystemRole::BranchManager,
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

/** @return array<string, mixed> */
function validContributionPlanFormData(array $overrides = []): array
{
    return [
        'name' => 'Monthly Builders Savings',
        'code' => 'BUILD-MONTHLY',
        'description' => 'A regular monthly savings plan.',
        'branch_id' => null,
        'group_id' => null,
        'plan_type' => ContributionPlanType::RegularSavings->value,
        'frequency' => ContributionFrequency::Monthly->value,
        'amount_type' => ContributionAmountType::Fixed->value,
        'fixed_amount_minor' => 12500,
        'minimum_amount_minor' => null,
        'maximum_amount_minor' => null,
        'start_date' => '2026-08-01',
        'end_date' => null,
        'collection_day_of_week' => null,
        'collection_day_of_month' => 15,
        'grace_period_days' => 3,
        'allow_partial_payments' => true,
        'allow_advance_payments' => false,
        'allow_overpayments' => false,
        'requires_approval_to_join' => true,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
});

test('contribution plans are organisation and branch scoped with organisation wide visibility', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    establishContributionPlanFilamentContext($actor, $organisation, branch: $activeBranch);
    $branchPlan = ContributionPlan::factory()
        ->for($organisation)
        ->for($activeBranch)
        ->create();
    $organisationPlan = ContributionPlan::factory()
        ->for($organisation)
        ->create(['branch_id' => null]);
    $otherBranchPlan = ContributionPlan::factory()
        ->for($organisation)
        ->for($otherBranch)
        ->create();
    $foreignPlan = ContributionPlan::factory()->for($foreignOrganisation)->create();

    $this->actingAs($actor);

    Livewire::test(ListContributionPlans::class)
        ->assertCanSeeTableRecords([$branchPlan, $organisationPlan])
        ->assertCanNotSeeTableRecords([$otherBranchPlan, $foreignPlan]);

    $loaded = ContributionPlanResource::getEloquentQuery()
        ->findOrFail($branchPlan->id);

    expect($loaded)
        ->relationLoaded('branch')->toBeTrue()
        ->relationLoaded('group')->toBeTrue()
        ->relationLoaded('createdBy')->toBeTrue()
        ->and($loaded->enrolments_count)->toBeInt()
        ->and($loaded->obligations_count)->toBeInt()
        ->and($loaded->payments_count)->toBeInt();
});

test('contribution plan direct urls reject foreign tenants branches and missing permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $activeBranch = Branch::factory()->for($organisation)->create();
    $otherBranch = Branch::factory()->for($organisation)->create();
    $assignment = establishContributionPlanFilamentContext(
        $actor,
        $organisation,
        branch: $activeBranch,
    );
    $foreignPlan = ContributionPlan::factory()->for($foreignOrganisation)->create();
    $otherBranchPlan = ContributionPlan::factory()
        ->for($organisation)
        ->for($otherBranch)
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
        ->get(ContributionPlanResource::getUrl('view', [
            'record' => $foreignPlan->public_id,
        ]))
        ->assertNotFound();

    $this->withSession($session)
        ->get(ContributionPlanResource::getUrl('view', [
            'record' => $otherBranchPlan->public_id,
        ]))
        ->assertNotFound();

    $assignment->role->revokePermissionTo(
        FoundationPermission::ContributionPlansView->value,
    );

    expect(ContributionPlanResource::canViewAny())->toBeFalse();

    $this->withSession($session)
        ->get(ContributionPlanResource::getUrl())
        ->assertForbidden();
});

test('contribution plan resource is read only responsive searchable and uses public uuids', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionPlanFilamentContext($actor, $organisation);
    $plan = ContributionPlan::factory()
        ->for($organisation)
        ->create([
            'name' => 'Monthly Builders Savings',
            'code' => 'BUILD-MONTHLY',
            'fixed_amount_minor' => 12500,
            'currency_code' => 'GHS',
            'status' => ContributionPlanStatus::Active,
        ]);

    $this->actingAs($actor);

    $component = Livewire::test(ListContributionPlans::class)
        ->searchTable('BUILD-MONTHLY')
        ->assertCanSeeTableRecords([$plan]);

    expect($component->instance()->getTable()->getColumn('plan_type')?->getVisibleFrom())
        ->toBe('md')
        ->and($component->instance()->getTable()->getColumn('fixed_amount_minor')?->getVisibleFrom())
        ->toBe('lg')
        ->and($component->instance()->getTable()->getColumn('group.name')?->getVisibleFrom())
        ->toBe('xl')
        ->and(ContributionPlanResource::getRecordRouteKeyName())->toBe('public_id')
        ->and(ContributionPlanResource::getGloballySearchableAttributes())
        ->toBe(['name', 'code'])
        ->and(ContributionPlanResource::getGlobalSearchResultUrl($plan))
        ->toContain($plan->public_id)
        ->and(ContributionPlanResource::getPages())->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit'])
        ->and(ContributionPlanResource::canCreate())->toBeFalse()
        ->and(ContributionPlanResource::canEdit($plan))->toBeFalse();

    Livewire::test(ViewContributionPlan::class, ['record' => $plan->public_id])
        ->assertSee('Monthly Builders Savings')
        ->assertSee('GHS 125.00')
        ->assertSee($plan->public_id);
});

test('plan creation delegates safe scoped configuration to the domain action', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['currency_code' => 'GHS']);
    $branch = Branch::factory()->for($organisation)->create();
    $group = Group::factory()->for($organisation)->for($branch)->create();
    establishContributionPlanFilamentContext($actor, $organisation);
    $created = ContributionPlan::factory()->draft()->for($organisation)->make([
        'branch_id' => $branch->id,
        'group_id' => $group->id,
    ]);

    $this->actingAs($actor);

    $createPlan = Mockery::mock(CreateContributionPlan::class);
    $createPlan->shouldReceive('handle')
        ->once()
        ->withArgs(function (
            User $selectedActor,
            array $attributes,
            ?Branch $selectedBranch,
            ?Group $selectedGroup,
        ) use ($actor, $branch, $group): bool {
            return $selectedActor->is($actor)
                && $selectedBranch?->is($branch) === true
                && $selectedGroup?->is($group) === true
                && $attributes['name'] === 'Monthly Builders Savings'
                && $attributes['fixed_amount_minor'] === 12500
                && $attributes['currency_code'] === 'GHS'
                && $attributes['collection_day_of_month'] === 15
                && ! array_key_exists('organisation_id', $attributes)
                && ! array_key_exists('status', $attributes)
                && ! array_key_exists('created_by_user_id', $attributes);
        })
        ->andReturn($created);
    app()->instance(CreateContributionPlan::class, $createPlan);

    Livewire::test(ListContributionPlans::class)
        ->assertActionVisible(TestAction::make('createPlan')->table())
        ->callAction(
            TestAction::make('createPlan')->table(),
            data: validContributionPlanFormData([
                'branch_id' => $branch->id,
                'group_id' => $group->id,
                'currency_code' => 'USD',
                'organisation_id' => Organisation::factory()->create()->id,
                'status' => ContributionPlanStatus::Active->value,
                'created_by_user_id' => User::factory()->create()->id,
            ]),
        )
        ->assertHasNoFormErrors()
        ->assertNotified('Contribution plan created');
});

test('plan updates delegate safe configuration and reject foreign scope tampering', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $foreignOrganisation = Organisation::factory()->create();
    $plan = ContributionPlan::factory()->draft()->for($organisation)->create();
    $foreignBranch = Branch::factory()->for($foreignOrganisation)->create();
    establishContributionPlanFilamentContext($actor, $organisation);
    $updated = $plan->replicate()->forceFill([
        'id' => $plan->id,
        'name' => 'Updated Savings Plan',
    ]);

    $this->actingAs($actor);

    $updatePlan = Mockery::mock(UpdateContributionPlan::class);
    $updatePlan->shouldReceive('handle')
        ->once()
        ->withArgs(fn (
            ContributionPlan $selectedPlan,
            array $attributes,
            ?Branch $selectedBranch,
            ?Group $selectedGroup,
        ): bool => $selectedPlan->is($plan)
            && $attributes['name'] === 'Updated Savings Plan'
            && $attributes['currency_code'] === $plan->currency_code
            && $selectedBranch === null
            && $selectedGroup === null
            && ! array_key_exists('status', $attributes))
        ->andReturn($updated);
    app()->instance(UpdateContributionPlan::class, $updatePlan);

    Livewire::test(ListContributionPlans::class)
        ->callAction(
            TestAction::make('editPlan')->table($plan),
            data: validContributionPlanFormData([
                'name' => 'Updated Savings Plan',
                'code' => $plan->code,
                'currency_code' => 'USD',
                'status' => ContributionPlanStatus::Archived->value,
            ]),
        )
        ->assertHasNoFormErrors()
        ->assertNotified('Contribution plan updated');

    Livewire::test(ListContributionPlans::class)
        ->callAction(
            TestAction::make('createPlan')->table(),
            data: validContributionPlanFormData([
                'code' => 'FOREIGN-SCOPE',
                'branch_id' => $foreignBranch->id,
            ]),
        )
        ->assertNotNotified('Contribution plan created');
});

test('plan lifecycle actions follow status and permission specific domain transitions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionPlanFilamentContext($actor, $organisation);
    $plan = ContributionPlan::factory()->draft()->for($organisation)->create();

    $this->actingAs($actor);

    Livewire::test(ListContributionPlans::class)
        ->assertActionVisible(TestAction::make('activate')->table($plan))
        ->callAction(TestAction::make('activate')->table($plan))
        ->assertNotified('Contribution plan activated');
    expect($plan->refresh()->status)->toBe(ContributionPlanStatus::Active);

    Livewire::test(ListContributionPlans::class)
        ->callAction(TestAction::make('suspend')->table($plan))
        ->assertNotified('Contribution plan suspended');
    expect($plan->refresh()->status)->toBe(ContributionPlanStatus::Suspended);

    Livewire::test(ListContributionPlans::class)
        ->callAction(TestAction::make('reinstate')->table($plan))
        ->assertNotified('Contribution plan reinstated');
    expect($plan->refresh()->status)->toBe(ContributionPlanStatus::Active);

    Livewire::test(ListContributionPlans::class)
        ->callAction(TestAction::make('close')->table($plan))
        ->assertNotified('Contribution plan closed');
    expect($plan->refresh()->status)->toBe(ContributionPlanStatus::Closed);

    Livewire::test(ListContributionPlans::class)
        ->callAction(TestAction::make('archive')->table($plan))
        ->assertNotified('Contribution plan archived');
    expect($plan->refresh()->status)->toBe(ContributionPlanStatus::Archived)
        ->and($plan->exists)->toBeTrue();
});

test('plan mutation actions are hidden without their explicit permissions', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionPlanFilamentContext(
        $actor,
        $organisation,
        SystemRole::Auditor,
    );
    $draft = ContributionPlan::factory()->draft()->for($organisation)->create();
    $active = ContributionPlan::factory()->active()->for($organisation)->create();

    $this->actingAs($actor);

    Livewire::test(ListContributionPlans::class)
        ->assertActionHidden(TestAction::make('createPlan')->table())
        ->assertActionHidden(TestAction::make('editPlan')->table($draft))
        ->assertActionHidden(TestAction::make('activate')->table($draft))
        ->assertActionHidden(TestAction::make('suspend')->table($active))
        ->assertActionHidden(TestAction::make('close')->table($active));
});
