<?php

namespace App\Domain\Membership\Queries;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberContact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMemberContacts
{
    /** @return LengthAwarePaginator<int, MemberContact> */
    public function handle(Member $member): LengthAwarePaginator
    {
        return MemberContact::query()
            ->whereBelongsTo($member)
            ->latest()
            ->paginate(25);
    }
}
