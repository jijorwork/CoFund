<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Enums\ObligationStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\ContributionObligation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EndContributionPlanEnrolment
{
    public function handle(
        MemberPlanEnrolment $enrolment,
        User $actor,
        CarbonImmutable $effectiveDate,
        string $reason,
    ): MemberPlanEnrolment {
        if (! in_array($enrolment->status, [EnrolmentStatus::Active, EnrolmentStatus::Suspended], true)) {
            throw new InvalidContributionOperation;
        }

        return DB::transaction(function () use ($enrolment, $actor, $effectiveDate, $reason): MemberPlanEnrolment {
            $enrolment->update([
                'status' => EnrolmentStatus::Ended,
                'end_date' => $effectiveDate->toDateString(),
                'ended_at' => now(),
                'ended_by_user_id' => $actor->getKey(),
                'end_reason' => $reason,
                'next_due_date' => null,
                'active_enrolment_key' => null,
            ]);

            ContributionObligation::query()
                ->whereBelongsTo($enrolment, 'enrolment')
                ->whereDate('due_date', '>', $effectiveDate->toDateString())
                ->whereIn('schedule_status', [ObligationStatus::Upcoming, ObligationStatus::Due])
                ->update(['schedule_status' => ObligationStatus::Cancelled]);

            return $enrolment->refresh()->load(['contributionPlan', 'member', 'branch', 'group']);
        });
    }
}
