<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Identity\Auditing\ActingContext;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberStatusHistory;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Membership\Services\MembershipNumberGenerator;
use App\Domain\Membership\Services\PhoneNumberNormalizer;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateMember
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
        private MembershipNumberGenerator $numbers,
        private PhoneNumberNormalizer $phones,
        private AddMemberContact $addContact,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function handle(User $actor, array $profile, ?Branch $branch = null): Member
    {
        $this->branchScope->assertAccessibleBranch($branch);
        $acting = ActingContext::from($actor, $this->tenantContext);

        return DB::transaction(function () use ($actor, $acting, $profile, $branch): Member {
            $contacts = $profile['contacts'] ?? [];
            unset($profile['contacts']);
            $joinedAt = isset($profile['joined_at'])
                ? now()->parse($profile['joined_at'])
                : now();
            $status = isset($profile['status'])
                ? MemberStatus::from($profile['status'])
                : MemberStatus::Pending;

            $member = Member::query()->create([
                ...$profile,
                'organisation_id' => $acting->organisationId,
                'branch_id' => $branch?->getKey(),
                'user_id' => null,
                'membership_number' => $this->numbers->next(
                    $this->tenantContext->organisation(),
                    $joinedAt->year,
                ),
                'phone' => $this->phones->normalize((string) $profile['phone']),
                'alternate_phone' => $this->phones->normalize($profile['alternate_phone'] ?? null),
                'joined_at' => $joinedAt->toDateString(),
                'status' => $status,
                'created_by_user_id' => $actor->getKey(),
            ]);

            MemberStatusHistory::query()->create([
                'organisation_id' => $acting->organisationId,
                'member_id' => $member->getKey(),
                'from_status' => null,
                'to_status' => $status,
                'reason' => 'Member registered',
                'effective_at' => now(),
                'changed_by_user_id' => $acting->userId,
                'acting_role_id' => $acting->roleId,
            ]);

            foreach ($contacts as $contact) {
                $this->addContact->handle($member, $contact);
            }

            return $member->load(['branch', 'user', 'contacts']);
        }, attempts: 3);
    }
}
