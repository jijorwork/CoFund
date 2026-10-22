<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Contributions\Enums\ContributionPlanStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Notifications\ValueObjects\NotificationDispatchData;
use Carbon\CarbonImmutable;

class DispatchContributionReminders
{
    public function __construct(private readonly CreateNotificationDispatch $dispatches) {}

    public function handle(): int
    {
        $created = 0;

        ContributionObligation::query()
            ->where('outstanding_amount_minor', '>', 0)
            ->whereNotIn('schedule_status', [ObligationStatus::Cancelled->value, ObligationStatus::Waived->value, ObligationStatus::Satisfied->value])
            ->whereHas('contributionPlan', fn ($query) => $query->where('status', ContributionPlanStatus::Active->value))
            ->whereHas('enrolment', fn ($query) => $query->where('status', EnrolmentStatus::Active->value))
            ->whereHas('member', fn ($query) => $query->where('status', MemberStatus::Active->value)->whereNotNull('user_id'))
            ->with(['organisation', 'enrolment.branch', 'member.user'])
            ->chunkById(100, function ($obligations) use (&$created): void {
                foreach ($obligations as $obligation) {
                    $organisation = $obligation->organisation;
                    $enrolment = $obligation->enrolment;
                    $member = $obligation->member;
                    if ($organisation === null || $enrolment->organisation_id !== $obligation->organisation_id
                        || $enrolment->member_id !== $member->getKey()
                        || $member->organisation_id !== $obligation->organisation_id || $member->user === null) {
                        continue;
                    }

                    $today = CarbonImmutable::now($organisation->timezone)->toDateString();
                    $dueDate = $obligation->due_date->toDateString();
                    $graceDate = $obligation->grace_due_date?->toDateString();
                    if ($dueDate > $today) {
                        continue;
                    }

                    $overdue = $graceDate !== null && $graceDate < $today;
                    $eventKey = $overdue ? 'contribution.obligation_overdue' : 'contribution.obligation_due';
                    $title = $overdue ? 'Contribution overdue' : 'Contribution due';
                    $body = sprintf(
                        'Your contribution of %s %s remains outstanding. Due date: %s.',
                        $obligation->currency_code,
                        number_format($obligation->outstanding_amount_minor / 100, 2),
                        $dueDate,
                    );

                    $dispatch = $this->dispatches->handle(new NotificationDispatchData(
                        organisation: $organisation,
                        branch: $enrolment->branch,
                        eventKey: $eventKey,
                        sourceType: 'contribution_obligation',
                        sourceUuid: $obligation->public_id,
                        recipient: $member->user,
                        title: $title,
                        body: $body,
                    ));
                    if ($dispatch->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
