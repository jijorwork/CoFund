<?php

namespace App\Domain\Welfare\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Welfare\Exceptions\InvalidWelfareOperation;
use App\Domain\Welfare\Models\WelfareProduct;
use App\Models\User;

class CreateWelfareProduct
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization) {}

    /** @param array<string,mixed> $attributes */
    public function handle(User $actor, array $attributes): WelfareProduct
    {
        $this->authorization->authorize(FoundationPermission::WelfareProductsCreate->value);
        $organisation = $this->context->organisation();
        if (strtoupper((string) $attributes['currency_code']) !== strtoupper($organisation->currency_code)) {
            throw new InvalidWelfareOperation('Welfare product currency must match the organisation.');
        }

        return WelfareProduct::query()->create([...$attributes, 'organisation_id' => $organisation->getKey(), 'created_by_user_id' => $actor->getKey()]);
    }
}
