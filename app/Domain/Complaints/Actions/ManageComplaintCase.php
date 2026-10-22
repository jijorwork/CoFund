<?php

namespace App\Domain\Complaints\Actions;

use App\Domain\Complaints\Enums\ComplaintStatus;
use App\Domain\Complaints\Exceptions\InvalidComplaintOperation;
use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Complaints\Models\ComplaintMessage;
use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Identity\Auditing\Models\AuditEvent;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Models\OrganisationUserRole;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ManageComplaintCase
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization) {}

    public function open(User $actor, ?Member $member, string $subject, string $category, string $body): ComplaintCase
    {
        $this->assertActor($actor);
        $this->authorization->authorize(FoundationPermission::ComplaintsCreate->value);
        $branchId = $member !== null ? $member->branch_id : $this->context->branch()?->getKey();

        if ($member !== null && $member->organisation_id !== $this->context->organisation()->getKey()) {
            throw new InvalidComplaintOperation('The member is outside the active organisation.');
        }
        $this->assertBranch($branchId);

        return DB::transaction(function () use ($actor, $member, $subject, $category, $body, $branchId): ComplaintCase {
            $case = ComplaintCase::query()->create([
                'organisation_id' => $this->context->organisation()->getKey(),
                'branch_id' => $branchId,
                'member_id' => $member?->getKey(),
                'reported_by_user_id' => $actor->getKey(),
                'subject' => $subject,
                'category' => $category,
            ]);
            $this->addMessageRecord($case, $actor, $body, 'complainant');
            $this->audit($case, $actor, 'complaint.opened');

            return $case->load(['member', 'reportedBy', 'assignedTo', 'messages.author']);
        });
    }

    public function respond(ComplaintCase $case, User $actor, string $body, string $visibility): ComplaintMessage
    {
        $this->assertActor($actor);
        $this->authorization->authorize(FoundationPermission::ComplaintsRespond->value);

        return DB::transaction(function () use ($case, $actor, $body, $visibility): ComplaintMessage {
            $locked = $this->scopedQuery()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === ComplaintStatus::Closed || ! in_array($visibility, ['internal', 'complainant'], true)) {
                throw new InvalidComplaintOperation('Correspondence is not allowed for this case.');
            }

            $message = $this->addMessageRecord($locked, $actor, $body, $visibility);
            $this->audit($locked, $actor, 'complaint.message_added', ['visibility' => $visibility]);

            return $message;
        });
    }

    public function assign(ComplaintCase $case, User $actor, User $assignee): ComplaintCase
    {
        $this->assertActor($actor);
        $this->authorization->authorize(FoundationPermission::ComplaintsManage->value);

        return DB::transaction(function () use ($case, $actor, $assignee): ComplaintCase {
            $locked = $this->scopedQuery()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();
            $eligible = OrganisationUserRole::query()->currentlyValid()
                ->where('organisation_id', $locked->organisation_id)
                ->where('user_id', $assignee->getKey())
                ->where(fn (Builder $query): Builder => $query->whereNull('branch_id')->orWhere('branch_id', $locked->branch_id))
                ->exists();

            if (! in_array($locked->status, [ComplaintStatus::Open, ComplaintStatus::InReview], true) || ! $eligible) {
                throw new InvalidComplaintOperation('The case cannot be assigned to this user.');
            }

            $locked->update(['assigned_to_user_id' => $assignee->getKey(), 'status' => ComplaintStatus::InReview]);
            $this->audit($locked, $actor, 'complaint.assigned', ['assignee_user_id' => (int) $assignee->getKey()]);

            return $locked->refresh();
        });
    }

    public function resolve(ComplaintCase $case, User $actor, string $summary): ComplaintCase
    {
        $this->assertActor($actor);
        $this->authorization->authorize(FoundationPermission::ComplaintsResolve->value);

        return DB::transaction(function () use ($case, $actor, $summary): ComplaintCase {
            $locked = $this->scopedQuery()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ComplaintStatus::Open, ComplaintStatus::InReview], true) || trim($summary) === '') {
                throw new InvalidComplaintOperation('The case cannot be resolved in its current state.');
            }

            $locked->update([
                'status' => ComplaintStatus::Resolved,
                'resolution_summary' => $summary,
                'resolved_at' => now(),
                'resolved_by_user_id' => $actor->getKey(),
            ]);
            $this->audit($locked, $actor, 'complaint.resolved');

            return $locked->refresh();
        });
    }

    public function close(ComplaintCase $case, User $actor): ComplaintCase
    {
        $this->assertActor($actor);
        $this->authorization->authorize(FoundationPermission::ComplaintsResolve->value);

        return DB::transaction(function () use ($case, $actor): ComplaintCase {
            $locked = $this->scopedQuery()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ComplaintStatus::Resolved) {
                throw new InvalidComplaintOperation('Only a resolved case can be closed.');
            }

            $locked->update(['status' => ComplaintStatus::Closed, 'closed_at' => now(), 'closed_by_user_id' => $actor->getKey()]);
            $this->audit($locked, $actor, 'complaint.closed');

            return $locked->refresh();
        });
    }

    public function reopen(ComplaintCase $case, User $actor): ComplaintCase
    {
        $this->assertActor($actor);
        $this->authorization->authorize(FoundationPermission::ComplaintsManage->value);

        return DB::transaction(function () use ($case, $actor): ComplaintCase {
            $locked = $this->scopedQuery()->whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [ComplaintStatus::Resolved, ComplaintStatus::Closed], true)) {
                throw new InvalidComplaintOperation('Only a resolved or closed case can be reopened.');
            }

            $locked->update([
                'status' => ComplaintStatus::InReview,
                'resolution_summary' => null,
                'resolved_at' => null,
                'resolved_by_user_id' => null,
                'closed_at' => null,
                'closed_by_user_id' => null,
            ]);
            $this->audit($locked, $actor, 'complaint.reopened');

            return $locked->refresh();
        });
    }

    /** @return Builder<ComplaintCase> */
    private function scopedQuery(): Builder
    {
        $query = ComplaintCase::query()->where('organisation_id', $this->context->organisation()->getKey());

        if ($this->context->branch() !== null) {
            $query->where('branch_id', $this->context->branch()->getKey());
        }

        return $query;
    }

    private function assertActor(User $actor): void
    {
        if ($this->context->roleAssignment()->user_id !== $actor->getKey()) {
            throw new InvalidComplaintOperation('The actor does not match the active role.');
        }
    }

    private function assertBranch(?int $branchId): void
    {
        if ($this->context->branch() !== null && $branchId !== $this->context->branch()->getKey()) {
            throw new InvalidComplaintOperation('The case is outside the active branch.');
        }
    }

    private function addMessageRecord(ComplaintCase $case, User $author, string $body, string $visibility): ComplaintMessage
    {
        if (trim($body) === '') {
            throw new InvalidComplaintOperation('A correspondence body is required.');
        }

        return $case->messages()->create([
            'organisation_id' => $case->organisation_id,
            'author_user_id' => $author->getKey(),
            'visibility' => $visibility,
            'body' => $body,
        ]);
    }

    /** @param array<string, int|string> $details */
    private function audit(ComplaintCase $case, User $actor, string $eventKey, array $details = []): void
    {
        $acting = ActingContext::from($actor, $this->context);

        AuditEvent::query()->create([
            'organisation_id' => $acting->organisationId,
            'branch_id' => $case->branch_id,
            'actor_user_id' => $acting->userId,
            'acting_role_id' => $acting->roleId,
            'role_assignment_id' => $acting->roleAssignmentId,
            'event_key' => $eventKey,
            'subject_type' => 'complaint_case',
            'subject_public_id' => $case->public_id,
            'details' => $details,
        ]);
    }
}
