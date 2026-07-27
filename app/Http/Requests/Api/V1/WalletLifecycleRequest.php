<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class WalletLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route()->getActionMethod() === 'close'
            ? FoundationPermission::WalletsClose
            : FoundationPermission::WalletsSuspend;

        return app(RoleAuthorization::class)->can($permission->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
