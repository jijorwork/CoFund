<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class ContributionPlanLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()->getActionMethod()) {
            'activate', 'reinstate' => FoundationPermission::ContributionPlansActivate,
            'suspend' => FoundationPermission::ContributionPlansSuspend,
            'close', 'archive' => FoundationPermission::ContributionPlansClose,
            default => FoundationPermission::ContributionPlansUpdate,
        };

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
