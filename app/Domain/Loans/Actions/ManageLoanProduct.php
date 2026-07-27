<?php

namespace App\Domain\Loans\Actions;

use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Loans\Enums\LoanProductStatus;
use App\Domain\Loans\Exceptions\InvalidLoanOperation;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;

class ManageLoanProduct
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization) {}

    /** @param array<string,mixed> $attributes */
    public function create(User $actor, array $attributes): LoanProduct
    {
        $this->authorization->authorize(FoundationPermission::LoanProductsCreate->value);
        $organisation = $this->context->organisation();

        return LoanProduct::query()->create([...$attributes, 'organisation_id' => $organisation->getKey(), 'currency_code' => strtoupper($organisation->currency_code), 'created_by_user_id' => $actor->getKey()]);
    }

    /** @param array<string,mixed> $attributes */
    public function update(LoanProduct $product, array $attributes): LoanProduct
    {
        $this->authorization->authorize(FoundationPermission::LoanProductsUpdate->value);
        $this->assertTenant($product);
        if (! in_array($product->status, [LoanProductStatus::Draft, LoanProductStatus::Suspended], true)) {
            throw new InvalidLoanOperation('Only draft or suspended products may be edited.');
        }
        $product->update($attributes);

        return $product->refresh();
    }

    public function transition(LoanProduct $product, LoanProductStatus $status): LoanProduct
    {
        $permission = in_array($status, [LoanProductStatus::Active], true) ? FoundationPermission::LoanProductsActivate : (in_array($status, [LoanProductStatus::Suspended], true) ? FoundationPermission::LoanProductsSuspend : FoundationPermission::LoanProductsClose);
        $this->authorization->authorize($permission->value);
        $this->assertTenant($product);
        $allowed = match ($status) {
            LoanProductStatus::Active => [LoanProductStatus::Draft, LoanProductStatus::Suspended],
            LoanProductStatus::Suspended => [LoanProductStatus::Active],
            LoanProductStatus::Closed => [LoanProductStatus::Active, LoanProductStatus::Suspended],
            LoanProductStatus::Archived => [LoanProductStatus::Closed],
            LoanProductStatus::Draft => [],
        };
        if (! in_array($product->status, $allowed, true)) {
            throw new InvalidLoanOperation('Invalid loan product lifecycle transition.');
        }
        $product->update(['status' => $status, 'activated_at' => $status === LoanProductStatus::Active ? now() : $product->activated_at, 'closed_at' => $status === LoanProductStatus::Closed ? now() : null]);

        return $product->refresh();
    }

    private function assertTenant(LoanProduct $product): void
    {
        if ($product->organisation_id !== $this->context->organisation()->getKey()) {
            throw new InvalidLoanOperation;
        }
    }
}
