<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Welfare\Enums\WelfareProductStatus;
use App\Domain\Welfare\Models\WelfareProduct;

class ArchiveWelfareProduct
{
    public function __construct(private ChangeWelfareProductStatus $change) {}

    public function handle(WelfareProduct $product): WelfareProduct
    {
        return $this->change->handle($product, WelfareProductStatus::Archived);
    }
}
