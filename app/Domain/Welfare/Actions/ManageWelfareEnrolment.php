<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareEnrolmentStatus;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareEnrolment;
use App\Models\User;

class ManageWelfareEnrolment
{
    public function __construct(private RoleAuthorization $authorization) {}

    public function approve(User $actor, WelfareEnrolment $enrolment): WelfareEnrolment
    {
        $this->authorization->authorize(FoundationPermission::WelfareEnrolmentsApprove->value);

        return $this->transition($enrolment, WelfareEnrolmentStatus::Active, ['approved_by_user_id' => $actor->getKey(), 'approved_at' => now(), 'activated_at' => now()]);
    }

    public function activate(WelfareEnrolment $enrolment): WelfareEnrolment
    {
        $this->authorization->authorize(FoundationPermission::WelfareEnrolmentsApprove->value);
        if ($enrolment->product->status !== WelfareProductStatus::Active) {
            throw new InvalidWelfareOperation('The welfare product is not active.');
        }

return $this->transition($enrolment, WelfareEnrolmentStatus::Active, ['activated_at' => now()]);
    }

    public function suspend(WelfareEnrolment $enrolment, string $reason): WelfareEnrolment
    {
        $this->authorization->authorize(FoundationPermission::WelfareEnrolmentsSuspend->value);
        if (trim($reason) === '') {
            throw new InvalidWelfareOperation('A suspension reason is required.');
        }

return $this->transition($enrolment, WelfareEnrolmentStatus::Suspended, ['suspended_at' => now(), 'suspension_reason' => $reason]);
    }

    public function reinstate(WelfareEnrolment $enrolment): WelfareEnrolment
    {
        $this->authorization->authorize(FoundationPermission::WelfareEnrolmentsSuspend->value);

        return $this->transition($enrolment, WelfareEnrolmentStatus::Active, ['suspended_at' => null, 'suspension_reason' => null]);
    }

    public function terminate(WelfareEnrolment $enrolment, string $reason): WelfareEnrolment
    {
        $this->authorization->authorize(FoundationPermission::WelfareEnrolmentsTerminate->value);
        if (trim($reason) === '') {
            throw new InvalidWelfareOperation('A termination reason is required.');
        }

return $this->transition($enrolment, WelfareEnrolmentStatus::Terminated, ['terminated_at' => now(), 'termination_reason' => $reason, 'active_enrolment_key' => null]);
    }

    /** @param array<string,mixed> $attributes */
    private function transition(WelfareEnrolment $enrolment, WelfareEnrolmentStatus $status, array $attributes): WelfareEnrolment
    {
        $enrolment->update([...$attributes, 'status' => $status]);

        return $enrolment->refresh();
    }
}
