<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable([
    'name',
    'token',
    'abilities',
    'expires_at',
    'organisation_id',
    'branch_id',
    'role_assignment_id',
    'role_id',
])]
class ContextualAccessToken extends PersonalAccessToken
{
    use HasUuids;

    protected $table = 'personal_access_tokens';

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
