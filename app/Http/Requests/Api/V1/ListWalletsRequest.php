<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Wallets\Enums\WalletOwnerType;
use App\Domain\Wallets\Enums\WalletStatus;
use App\Domain\Wallets\Enums\WalletType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWalletsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RoleAuthorization::class)->can(FoundationPermission::WalletsView->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:wallet_type,status,branch_uuid,member_uuid,owner_type,currency_code'],
            'filter.wallet_type' => ['sometimes', Rule::enum(WalletType::class)],
            'filter.status' => ['sometimes', Rule::enum(WalletStatus::class)],
            'filter.branch_uuid' => ['sometimes', 'uuid'],
            'filter.member_uuid' => ['sometimes', 'uuid'],
            'filter.owner_type' => ['sometimes', Rule::enum(WalletOwnerType::class)],
            'filter.currency_code' => ['sometimes', 'string', 'size:3'],
            'sort' => ['sometimes', Rule::in(['name', '-name', 'code', '-code', 'opened_at', '-opened_at', 'created_at', '-created_at'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
