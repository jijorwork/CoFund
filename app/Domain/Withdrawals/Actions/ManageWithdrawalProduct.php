<?php

namespace App\Domain\Withdrawals\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Withdrawals\Enums\WithdrawalProductStatus;
use App\Domain\Withdrawals\Exceptions\InvalidWithdrawalOperation;
use App\Domain\Withdrawals\Models\WithdrawalProduct;
use App\Domain\Withdrawals\Models\WithdrawalRequest;

class ManageWithdrawalProduct
{
    public function __construct(private RoleAuthorization $authorization) {}

    /** @param array<string, mixed> $attributes */
    public function update(WithdrawalProduct $product, array $attributes): WithdrawalProduct
    {
        $this->authorization->authorize(FoundationPermission::WithdrawalProductsUpdate->value);
        if (WithdrawalRequest::query()->where('withdrawal_product_id', $product->getKey())->whereHas('payouts', fn ($query) => $query->where('status', 'posted'))->exists()) {
            throw new InvalidWithdrawalOperation('Material rules cannot change after a paid withdrawal.');
        }
        $product->update($attributes);

        return $product->refresh();
    }

    public function transition(WithdrawalProduct $product, WithdrawalProductStatus $status): WithdrawalProduct
    {
        $permission = match ($status) {
            WithdrawalProductStatus::Active => FoundationPermission::WithdrawalProductsActivate,
            WithdrawalProductStatus::Suspended => FoundationPermission::WithdrawalProductsSuspend,
            WithdrawalProductStatus::Closed, WithdrawalProductStatus::Archived => FoundationPermission::WithdrawalProductsClose,
            WithdrawalProductStatus::Draft => throw new InvalidWithdrawalOperation('Products cannot return to draft.'),
        };
        $this->authorization->authorize($permission->value);
        if ($status === WithdrawalProductStatus::Active && ($product->fee_type !== 'none' && $product->fee_value === null)) {
            throw new InvalidWithdrawalOperation('Configured withdrawal fees require a value.');
        }
        $product->update(['status' => $status, 'activated_at' => $status === WithdrawalProductStatus::Active ? now() : $product->activated_at, 'suspended_at' => $status === WithdrawalProductStatus::Suspended ? now() : null, 'closed_at' => in_array($status, [WithdrawalProductStatus::Closed, WithdrawalProductStatus::Archived], true) ? now() : null]);

        return $product->refresh();
    }
}
