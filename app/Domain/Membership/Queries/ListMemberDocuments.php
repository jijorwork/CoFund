<?php

namespace App\Domain\Membership\Queries;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMemberDocuments
{
    /** @return LengthAwarePaginator<int, MemberDocument> */
    public function handle(Member $member): LengthAwarePaginator
    {
        return MemberDocument::query()
            ->where('organisation_id', $member->organisation_id)
            ->where('documentable_type', 'member')
            ->where('documentable_id', $member->getKey())
            ->latest()
            ->paginate(25);
    }
}
