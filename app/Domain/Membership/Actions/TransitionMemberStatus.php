<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Identity\Auditing\RecordMemberStatusAudit;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberStatusHistory;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class TransitionMemberStatus
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
        private RecordMemberStatusAudit $audit,
    ) {}

    public function handle(
        Member $member,
        MemberStatus $to,
        User $actor,
        ?string $reason = null,
        ?CarbonInterface $effectiveAt = null,
    ): Member {
        $this->branchScope->assertAccessibleRecord($member->branch_id);
        $acting = ActingContext::from($actor, $this->tenantContext);

        return DB::transaction(function () use ($member, $to, $acting, $reason, $effectiveAt): Member {
            $locked = Member::query()->lockForUpdate()->whereKey($member->getKey())->firstOrFail();
            $from = $locked->status;

            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new InvalidMembershipOperation('The membership status transition is not allowed.');
            }

            $changes = ['status' => $to];

            if ($to === MemberStatus::Suspended) {
                if ($reason === null || trim($reason) === '') {
                    throw new InvalidMembershipOperation('A suspension reason is required.');
                }
                $changes += [
                    'suspended_at' => now(),
                    'suspended_by_user_id' => $acting->userId,
                    'suspension_reason' => $reason,
                ];
            }

            if ($to === MemberStatus::Active) {
                $changes += [
                    'suspended_at' => null,
                    'suspended_by_user_id' => null,
                    'suspension_reason' => null,
                    'exited_at' => null,
                    'exit_reason' => null,
                ];
            }

            if ($to === MemberStatus::Exited) {
                $changes += [
                    'exited_at' => ($effectiveAt ?? now())->toDateString(),
                    'exit_reason' => $reason,
                    'active_user_link_key' => null,
                ];
            }

            if ($to === MemberStatus::Deceased) {
                $changes += [
                    'deceased_at' => ($effectiveAt ?? now())->toDateString(),
                    'active_user_link_key' => null,
                ];
            }

            $locked->update($changes);

            MemberStatusHistory::query()->create([
                'organisation_id' => $acting->organisationId,
                'member_id' => $locked->getKey(),
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'effective_at' => $effectiveAt ?? now(),
                'changed_by_user_id' => $acting->userId,
                'acting_role_id' => $acting->roleId,
            ]);

            $this->audit->handle($locked, $from, $to, $acting, $reason);

            return $locked->refresh()->load(['branch', 'user']);
        });
    }
}
