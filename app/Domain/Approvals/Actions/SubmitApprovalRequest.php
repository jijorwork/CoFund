<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Data\ApprovalDefinition;
use App\Domain\Approvals\Exceptions\InvalidApprovalOperation;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Identity\Auditing\RecordApprovalAudit;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitApprovalRequest
{
    public function __construct(
        private TenantContext $context,
        private RoleAuthorization $authorization,
        private RecordApprovalAudit $audit,
    ) {}

    public function handle(
        User $requester,
        Model $subject,
        ApprovalDefinition $definition,
    ): ApprovalRequest {
        $this->authorization->authorize($definition->submitPermission->value);

        $organisationId = (int) $this->context->organisation()->getKey();
        $branchId = $subject->getAttribute('branch_id');
        $subjectPublicId = $subject->getAttribute('public_id');

        if (
            ! $subject->exists
            || (int) $subject->getAttribute('organisation_id') !== $organisationId
            || ! is_string($subjectPublicId)
            || ! Str::isUuid($subjectPublicId)
            || ($this->context->branch() !== null
                && (int) $branchId !== $this->context->branch()->getKey())
            || $this->context->roleAssignment()->user_id !== $requester->getKey()
        ) {
            throw new InvalidApprovalOperation('The approval subject or requester is outside the active context.');
        }

        $subjectType = $subject->getMorphClass();
        $activeSubjectKey = hash('sha256', implode(':', [
            $organisationId,
            $definition->key,
            $subjectType,
            $subjectPublicId,
        ]));

        if (ApprovalRequest::query()->where('active_subject_key', $activeSubjectKey)->exists()) {
            throw new InvalidApprovalOperation('An approval request is already pending for this subject.');
        }

        return DB::transaction(function () use ($organisationId, $branchId, $definition, $subjectType, $subjectPublicId, $requester, $activeSubjectKey): ApprovalRequest {
            $request = ApprovalRequest::query()->create([
                'organisation_id' => $organisationId,
                'branch_id' => $branchId,
                'workflow_key' => $definition->key,
                'workflow_version' => $definition->version,
                'subject_type' => $subjectType,
                'subject_public_id' => $subjectPublicId,
                'requested_by_user_id' => $requester->getKey(),
                'step_permissions' => array_map(
                    static fn ($permission): string => $permission->value,
                    $definition->stepPermissions,
                ),
                'active_subject_key' => $activeSubjectKey,
                'submitted_at' => now(),
            ]);

            $this->audit->handle($request, ActingContext::from($requester, $this->context), 'approval.submitted');

            return $request;
        });
    }
}
