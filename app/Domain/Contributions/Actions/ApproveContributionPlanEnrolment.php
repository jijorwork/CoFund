<?php

namespace App\Domain\Contributions\Actions;

use App\Domain\Approvals\Rules\EnsureDifferentActor;
use App\Domain\Contributions\Enums\ApprovalStatus;
use App\Domain\Contributions\Enums\EnrolmentStatus;
use App\Domain\Contributions\Exceptions\InvalidContributionOperation;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Contributions\Services\ContributionScope;
use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApproveContributionPlanEnrolment
{
    public function __construct(
        private EnsureDifferentActor $differentActor,
        private GenerateInitialContributionSchedule $schedule,
        private TenantContext $context,
        private ContributionScope $scope,
    ) {}

    public function handle(MemberPlanEnrolment $enrolment, User $actor): MemberPlanEnrolment
    {
        $this->scope->assertAccessibleEnrolment($enrolment);

        try {
            $this->differentActor->handle(
                ActingContext::from($actor, $this->context),
                $enrolment->created_by_user_id,
            );
        } catch (DomainException) {
            throw new InvalidContributionOperation;
        }

        return DB::transaction(function () use ($enrolment, $actor): MemberPlanEnrolment {
            $locked = MemberPlanEnrolment::query()
                ->lockForUpdate()
                ->whereKey($enrolment->getKey())
                ->firstOrFail();

            if (
                $locked->status !== EnrolmentStatus::Pending
                || $locked->approval_status !== ApprovalStatus::Pending
            ) {
                throw new InvalidContributionOperation;
            }

            $locked->update([
                'status' => EnrolmentStatus::Active,
                'approval_status' => ApprovalStatus::Approved,
                'approved_by_user_id' => $actor->getKey(),
                'approved_at' => now(),
            ]);

            return $this->schedule->handle($locked->refresh());
        });
    }
}
