<?php

namespace App\Domain\Collections\Queries;

use App\Domain\Collections\Models\Receipt;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindReceiptByUuid
{
    public function __construct(private TenantContext $context) {}

    public function handle(string $uuid): ?Receipt
    {
        $query = Receipt::query()
            ->whereBelongsTo($this->context->organisation())
            ->where('public_id', $uuid)
            ->with(['organisation', 'branch', 'member', 'issuedBy', 'payment', 'loanRepayment', 'welfareBenefitPayment.claim', 'withdrawalPayout.request.product', 'financialTransaction']);

        if ($this->context->roleAssignment()->branch_id !== null) {
            $query->where('branch_id', $this->context->branch()?->getKey());
        }
        if ($this->context->role()->name === 'collector') {
            $query->whereHas('payment', fn ($query) => $query->where(
                'collected_by_user_id',
                $this->context->roleAssignment()->user_id,
            ));
        }

        return $query->first();
    }
}
