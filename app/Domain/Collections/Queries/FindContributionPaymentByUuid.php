<?php

namespace App\Domain\Collections\Queries;

use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Identity\Enums\SystemRole;
use App\Domain\Organisations\Tenancy\TenantContext;

class FindContributionPaymentByUuid
{
    public function __construct(private TenantContext $context) {}

    public function handle(string $uuid): ?ContributionPayment
    {
        $query = ContributionPayment::query()
            ->whereBelongsTo($this->context->organisation())
            ->where('public_id', $uuid);

        if ($this->context->roleAssignment()->branch_id !== null) {
            $query->where('branch_id', $this->context->branch()?->getKey());
        }

        if ($this->context->role()->name === SystemRole::Collector->value) {
            $query->where('collected_by_user_id', $this->context->roleAssignment()->user_id);
        }

        return $query->with([
            'member', 'contributionPlan', 'enrolment', 'financialTransaction',
            'sourceWallet', 'destinationWallet', 'allocations.obligation',
            'receipt', 'collectedBy', 'reversalPayment',
        ])->first();
    }
}
