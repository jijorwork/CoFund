<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareProduct;

class UpdateWelfareProduct
{
    public function __construct(private RoleAuthorization $authorization) {}

    /** @param array<string,mixed> $attributes */
    public function handle(WelfareProduct $product, array $attributes): WelfareProduct
    {
        $this->authorization->authorize(FoundationPermission::WelfareProductsUpdate->value);
        if (! in_array($product->status, [WelfareProductStatus::Draft, WelfareProductStatus::Suspended], true)) {
            throw new InvalidWelfareOperation('Only draft or suspended welfare products may be updated.');
        }
        $product->update($attributes);

        return $product->refresh();
    }
}
