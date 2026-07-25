<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class ExtendContributionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::ContributionSchedulesGenerate->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
