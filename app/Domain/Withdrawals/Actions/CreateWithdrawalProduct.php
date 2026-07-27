<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Models\User;

class CreateWithdrawalProduct
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): WithdrawalProduct
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalProductsCreate->value);
        $organisation = $this->context->organisation();
        if (($attributes['currency_code'] ?? null) !== $organisation->currency_code) {
            throw new InvalidWithdrawalOperation('Withdrawal product currency must match the organisation currency.');
        }

        return WithdrawalProduct::query()->create([...$attributes, 'organisation_id' => $organisation->getKey(), 'created_by_user_id' => $actor->getKey()]);
    }
}
