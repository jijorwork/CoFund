<?php

namespace App\Domain\Identity\Auditing;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Auditing\Models\AuditEvent;
use DomainException;

class RecordApprovalAudit
{
    /** @param array<string, int|string|null> $details */
    public function handle(ApprovalRequest $request, ActingContext $acting, string $eventKey, array $details = []): AuditEvent
    {
        if ($request->organisation_id !== $acting->organisationId) {
            throw new DomainException('The audit subject is outside the acting organisation.');
        }

        return AuditEvent::query()->create([
            'organisation_id' => $acting->organisationId,
            'branch_id' => $request->branch_id,
            'actor_user_id' => $acting->userId,
            'acting_role_id' => $acting->roleId,
            'role_assignment_id' => $acting->roleAssignmentId,
            'event_key' => $eventKey,
            'subject_type' => 'approval_request',
            'subject_public_id' => $request->public_id,
            'details' => ['workflow_key' => $request->workflow_key, 'subject_public_id' => $request->subject_public_id, ...$details],
        ]);
    }
}
