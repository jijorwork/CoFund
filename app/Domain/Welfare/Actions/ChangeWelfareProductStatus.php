<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareProduct;

class ChangeWelfareProductStatus
{
    public function __construct(private RoleAuthorization $authorization) {}

    public function handle(WelfareProduct $product, WelfareProductStatus $status): WelfareProduct
    {
        $permission = match ($status) {
            WelfareProductStatus::Active => FoundationPermission::WelfareProductsActivate, WelfareProductStatus::Suspended => FoundationPermission::WelfareProductsSuspend, default => FoundationPermission::WelfareProductsClose
        };
        $this->authorization->authorize($permission->value);
        if ($status === WelfareProductStatus::Active && ($product->benefit_calculation_type === 'fixed' && $product->fixed_benefit_minor === null)) {
            throw new InvalidWelfareOperation('Fixed welfare products require a benefit amount.');
        }
        $product->update(['status' => $status, 'activated_at' => $status === WelfareProductStatus::Active ? now() : $product->activated_at, 'suspended_at' => $status === WelfareProductStatus::Suspended ? now() : null, 'closed_at' => in_array($status, [WelfareProductStatus::Closed, WelfareProductStatus::Archived], true) ? now() : null]);

        return $product->refresh();
    }
}
