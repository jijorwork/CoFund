<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\DocumentVerificationStatus;
use App\Domain\Membership\Exceptions\InvalidMembershipOperation;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use App\Domain\Membership\Services\MembershipBranchScope;
use App\Models\User;

class ReviewMemberDocument
{
    public function __construct(private MembershipBranchScope $branchScope) {}

    public function handle(
        Member $member,
        MemberDocument $document,
        User $actor,
        DocumentVerificationStatus $status,
        ?string $reason = null,
    ): MemberDocument {
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

        $document->update([
            'verification_status' => $status,
            'verified_by_user_id' => $actor->getKey(),
            'verified_at' => now(),
            'rejection_reason' => $status === DocumentVerificationStatus::Rejected ? $reason : null,
        ]);

        return $document->refresh();
    }
}
