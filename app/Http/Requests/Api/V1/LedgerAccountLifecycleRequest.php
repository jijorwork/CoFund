<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class LedgerAccountLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()->getActionMethod()) {
            'close' => FoundationPermission::LedgerAccountsClose,
            default => FoundationPermission::LedgerAccountsActivate,
        };

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
