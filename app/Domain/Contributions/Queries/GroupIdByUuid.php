<?php

namespace App\Domain\Contributions\Queries;

use App\Domain\Membership\Models\Group;
use Illuminate\Database\Eloquent\Builder;

class GroupIdByUuid
{
    /** @return Builder<Group> */
    public static function query(string $uuid): Builder
    {
        return Group::query()->select('id')->where('public_id', $uuid);
    }
}
