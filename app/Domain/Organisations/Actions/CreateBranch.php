<?php

namespace App\Domain\Organisations\Actions;

use App\Domain\Organisations\Enums\BranchStatus;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Models\Organisation;
use Illuminate\Support\Str;

class CreateBranch
{
    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     phone?: string|null,
     *     email?: string|null,
     *     address?: string|null,
     *     status?: BranchStatus
     * }  $attributes
     */
    public function handle(Organisation $organisation, array $attributes): Branch
    {
        return $organisation->branches()->create([
            ...$attributes,
            'public_id' => (string) Str::uuid7(),
            'code' => Str::upper($attributes['code']),
            'status' => $attributes['status'] ?? BranchStatus::Active,
        ]);
    }
}
