<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::WalletsCreate->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'owner_type' => ['required', Rule::enum(WalletOwnerType::class)],
            'owner_uuid' => ['required_unless:owner_type,organisation', 'nullable', 'uuid'],
            'branch_uuid' => ['required_if:owner_type,branch,collector_user', 'nullable', 'uuid'],
            'wallet_type' => ['required', Rule::enum(WalletType::class)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
        ];
    }
}
