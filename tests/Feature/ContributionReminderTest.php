<?php

use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\ContributionPlan;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Domain\Notifications\Actions\DispatchContributionReminders;
use App\Domain\Notifications\Models\NotificationDispatch;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Support\Facades\Queue;

test('contribution reminders are sent once for due and overdue obligations', function () {
    Queue::fake();
    $this->travelTo('2026-10-22 12:00:00');
    $organisation = Organisation::factory()->create(['timezone' => 'Africa/Accra']);
    $plan = ContributionPlan::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->linkedToUser()->create();
    $enrolment = MemberPlanEnrolment::factory()->for($plan, 'contributionPlan')->for($member)->create([
        'organisation_id' => $organisation->id,
    ]);
    $obligation = ContributionObligation::factory()->for($enrolment, 'enrolment')->create([
        'organisation_id' => $organisation->id,
        'contribution_plan_id' => $plan->id,
        'member_id' => $member->id,
        'due_date' => '2026-10-22',
        'grace_due_date' => '2026-10-25',
    ]);

    $reminders = app(DispatchContributionReminders::class);
    expect($reminders->handle())->toBe(1)
        ->and($reminders->handle())->toBe(0);
    $due = NotificationDispatch::query()->sole();
    expect($due->event_key)->toBe('contribution.obligation_due')
        ->and($due->organisation_id)->toBe($organisation->id)
        ->and($due->user_id)->toBe($member->user_id)
        ->and($due->source_uuid)->toBe($obligation->public_id);

    $this->travelTo('2026-10-26 12:00:00');
    expect($reminders->handle())->toBe(1)
        ->and($reminders->handle())->toBe(0)
        ->and(NotificationDispatch::query()->count())->toBe(2);
    expect(NotificationDispatch::query()->where('event_key', 'contribution.obligation_overdue')->sole()->user_id)->toBe($member->user_id);
});

test('reminders skip paid, future, and inactive enrolments', function () {
    Queue::fake();
    $this->travelTo('2026-10-22 12:00:00');
    $organisation = Organisation::factory()->create(['timezone' => 'Africa/Accra']);
    $plan = ContributionPlan::factory()->for($organisation)->create();
    $member = Member::factory()->for($organisation)->linkedToUser()->create();
    $enrolment = MemberPlanEnrolment::factory()->for($plan, 'contributionPlan')->for($member)->create([
        'organisation_id' => $organisation->id,
    ]);
    foreach ([
        ['outstanding_amount_minor' => 0, 'due_date' => '2026-10-21'],
        ['due_date' => '2026-10-24'],
        ['schedule_status' => 'cancelled', 'due_date' => '2026-10-20'],
    ] as $state) {
        ContributionObligation::factory()->for($enrolment, 'enrolment')->create(array_merge([
            'organisation_id' => $organisation->id,
            'contribution_plan_id' => $plan->id,
            'member_id' => $member->id,
        ], $state));
    }
    expect(app(DispatchContributionReminders::class)->handle())->toBe(0);
    $enrolment->update(['status' => 'suspended']);
    ContributionObligation::factory()->for($enrolment, 'enrolment')->create([
        'organisation_id' => $organisation->id,
        'contribution_plan_id' => $plan->id,
        'member_id' => $member->id,
    ]);

    expect(app(DispatchContributionReminders::class)->handle())->toBe(0)
        ->and(NotificationDispatch::query()->count())->toBe(0);
});

test('reminders reject suspended plans and inconsistent tenant links', function () {
    Queue::fake();
    $this->travelTo('2026-10-22 12:00:00');
    $organisation = Organisation::factory()->create(['timezone' => 'Africa/Accra']);
    $otherOrganisation = Organisation::factory()->create();
    $plan = ContributionPlan::factory()->for($organisation)->suspended()->create();
    $member = Member::factory()->for($organisation)->linkedToUser()->create();
    $enrolment = MemberPlanEnrolment::factory()->for($plan, 'contributionPlan')->for($member)->create([
        'organisation_id' => $organisation->id,
    ]);
    $obligation = ContributionObligation::factory()->for($enrolment, 'enrolment')->create([
        'organisation_id' => $organisation->id,
        'contribution_plan_id' => $plan->id,
        'member_id' => $member->id,
    ]);

    $reminders = app(DispatchContributionReminders::class);
    expect($reminders->handle())->toBe(0);
    $plan->update(['status' => 'active']);
    $obligation->update(['organisation_id' => $otherOrganisation->id]);
    expect($reminders->handle())->toBe(0)
        ->and(NotificationDispatch::query()->count())->toBe(0);
});
