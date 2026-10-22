<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewMemberDocument
{
    public function __construct(
        private TenantContext $tenantContext,
        private MembershipBranchScope $branchScope,
    ) {}

    public function handle(
        Member $member,
        MemberDocument $document,
        User $actor,
        DocumentVerificationStatus $status,
        ?string $reason = null,
    ): MemberDocument {
        if (
            $member->organisation_id !== $this->tenantContext->organisation()->getKey()
            || $document->organisation_id !== $this->tenantContext->organisation()->getKey()
        ) {
            throw new InvalidMembershipOperation;
        }

        $this->branchScope->assertAccessibleRecord($member->branch_id);

        if (
            $document->documentable_type !== 'member'
            || $document->documentable_id !== $member->getKey()
        ) {
            throw new InvalidMembershipOperation;
        }

        if ($status === DocumentVerificationStatus::Rejected && blank($reason)) {
            throw new InvalidMembershipOperation;
        }

        return DB::transaction(function () use ($member, $document, $actor, $status, $reason): MemberDocument {
            $lockedDocument = MemberDocument::query()
                ->whereKey($document->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedDocument->organisation_id !== $this->tenantContext->organisation()->getKey()
                || $lockedDocument->documentable_type !== 'member'
                || $lockedDocument->documentable_id !== $member->getKey()
                || $lockedDocument->verification_status !== DocumentVerificationStatus::Pending
            ) {
                throw new InvalidMembershipOperation;
            }

            $lockedDocument->update([
                'verification_status' => $status,
                'verified_by_user_id' => $actor->getKey(),
                'verified_at' => now(),
                'rejection_reason' => $status === DocumentVerificationStatus::Rejected ? $reason : null,
            ]);

            return $lockedDocument->refresh();
        });
    }
}
