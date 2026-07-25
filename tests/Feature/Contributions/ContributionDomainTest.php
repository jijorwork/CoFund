<?php

use App\Domain\Contributions\Actions\ApproveContributionPlanEnrolment;
use App\Domain\Contributions\Actions\EndContributionPlanEnrolment;
use App\Domain\Contributions\Actions\EnrolMemberInContributionPlan;
use App\Domain\Contributions\Actions\ExtendContributionSchedule;
use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\ContributionPositionStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Services\CalculateContributionPosition;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Models\Role;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Organisation;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemAuthorizationSeeder;

function establishContributionDomainContext(User $actor, Organisation $organisation): void
{
    $assignment = OrganisationUserRole::factory()
        ->for($actor)
        ->for($organisation)
        ->state(['role_id' => Role::findByName(SystemRole::OrganisationAdmin->value)->getKey()])
        ->create();

    app(TenantContext::class)->setOrganisation($organisation);
    app(TenantContext::class)->setBranch(null);
    app(TenantContext::class)->setRoleAssignment($assignment);
}

beforeEach(function () {
    $this->seed(SystemAuthorizationSeeder::class);
    CarbonImmutable::setTestNow('2026-07-25 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('schedule generation is deterministic and bounded by frequency horizons', function (string $frequency, int $expected) {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create(['timezone' => 'Africa/Accra']);
    establishContributionDomainContext($actor, $organisation);
    $member = Member::factory()->active()->for($organisation)->create();
    $factory = ContributionPlan::factory()->active()->for($organisation)->state([
        'start_date' => '2026-07-25',
        'requires_approval_to_join' => false,
    ]);
    $plan = match ($frequency) {
        'daily' => $factory->daily()->create(),
        'weekly' => $factory->weekly()->state(['collection_day_of_week' => 6])->create(),
        'monthly' => $factory->monthly()->state(['collection_day_of_month' => 25])->create(),
        'one-time' => $factory->oneTime()->create(),
    };

    $enrolment = app(EnrolMemberInContributionPlan::class)->handle(
        $actor,
        $member,
        $plan,
        null,
        CarbonImmutable::parse('2026-07-25'),
        null,
    );
    app(ExtendContributionSchedule::class)->handle($enrolment);

    expect($enrolment->obligations()->count())->toBe($expected)
        ->and($enrolment->obligations()->distinct()->count('due_date'))->toBe($expected);
})->with([
    'daily' => ['daily', 60],
    'weekly' => ['weekly', 12],
    'monthly' => ['monthly', 12],
    'one-time' => ['one-time', 1],
]);

test('flexible plans intentionally have no generated obligation schedule', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionDomainContext($actor, $organisation);
    $member = Member::factory()->active()->for($organisation)->create();
    $plan = ContributionPlan::factory()->active()->flexible()->for($organisation)->create();

    $enrolment = app(EnrolMemberInContributionPlan::class)->handle(
        $actor,
        $member,
        $plan,
        null,
        CarbonImmutable::parse('2026-07-25'),
        null,
    );

    expect($enrolment->obligations()->count())->toBe(0)
        ->and(app(CalculateContributionPosition::class)->calculate($enrolment)->status)
        ->toBe(ContributionPositionStatus::NoSchedule);
});

test('approval enforces separation of duties and creates the schedule once', function () {
    $maker = User::factory()->create();
    $approver = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionDomainContext($maker, $organisation);
    $member = Member::factory()->active()->for($organisation)->create();
    $plan = ContributionPlan::factory()->active()->daily()->for($organisation)->create([
        'requires_approval_to_join' => true,
    ]);
    $enrolment = app(EnrolMemberInContributionPlan::class)->handle(
        $maker,
        $member,
        $plan,
        null,
        CarbonImmutable::parse('2026-07-25'),
        null,
    );

    expect($enrolment->status)->toBe(EnrolmentStatus::Pending)
        ->and($enrolment->approval_status)->toBe(ApprovalStatus::Pending)
        ->and($enrolment->obligations()->count())->toBe(0);

    app(ApproveContributionPlanEnrolment::class)->handle($enrolment, $maker);
})->throws(InvalidContributionOperation::class);

test('ending an enrolment preserves due obligations and cancels only future ones', function () {
    $actor = User::factory()->create();
    $organisation = Organisation::factory()->create();
    establishContributionDomainContext($actor, $organisation);
    $member = Member::factory()->active()->for($organisation)->create();
    $plan = ContributionPlan::factory()->active()->daily()->for($organisation)->create(['start_date' => '2026-07-25']);
    $enrolment = app(EnrolMemberInContributionPlan::class)->handle(
        $actor,
        $member,
        $plan,
        null,
        CarbonImmutable::parse('2026-07-25'),
        null,
    );

    app(EndContributionPlanEnrolment::class)->handle(
        $enrolment,
        $actor,
        CarbonImmutable::parse('2026-07-26'),
        'Member requested closure',
    );

    expect($enrolment->refresh()->status)->toBe(EnrolmentStatus::Ended)
        ->and($enrolment->obligations()->whereDate('due_date', '<=', '2026-07-26')->where('status', '!=', 'cancelled')->count())->toBe(2)
        ->and($enrolment->obligations()->whereDate('due_date', '>', '2026-07-26')->where('status', 'cancelled')->count())->toBe(58);
});
