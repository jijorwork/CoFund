<?php

namespace App\Domain\Contributions\Queries;

use App\Domain\Organisations\Models\Branch;
use Illuminate\Database\Eloquent\Builder;

class BranchIdByUuid
{
    /** @return Builder<Branch> */
    public static function query(string $uuid): Builder
    {
        return Branch::query()->select('id')->where('public_id', $uuid);
    }
}
