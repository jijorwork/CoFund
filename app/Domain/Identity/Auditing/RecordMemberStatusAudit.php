<?php

namespace App\Domain\Identity\Auditing;

use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use DomainException;

class RecordMemberStatusAudit
{
    public function handle(
        Member $member,
        MemberStatus $from,
        MemberStatus $to,
        ActingContext $acting,
        ?string $reason,
    ): AuditEvent {
        if ($member->organisation_id !== $acting->organisationId) {
            throw new DomainException('The audit subject is outside the acting organisation.');
        }

        return AuditEvent::query()->create([
            'organisation_id' => $acting->organisationId,
            'branch_id' => $member->branch_id,
            'actor_user_id' => $acting->userId,
            'acting_role_id' => $acting->roleId,
            'role_assignment_id' => $acting->roleAssignmentId,
            'event_key' => 'member.status_changed',
            'subject_type' => 'member',
            'subject_public_id' => $member->public_id,
            'details' => [
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
            ],
        ]);
    }
}
