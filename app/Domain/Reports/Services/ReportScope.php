<?php

namespace App\Domain\Reports\Services;

use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportScope
{
    public function __construct(private TenantContext $context) {}

    public function branch(?string $branchUuid): ?Branch
    {
        $activeBranch = $this->context->branch();
        if ($this->context->roleAssignment()->branch_id !== null) {
            if ($branchUuid !== null && $branchUuid !== $activeBranch?->public_id) {
                throw new NotFoundHttpException;
            }

            return $activeBranch;
        }

        if ($branchUuid === null) {
            return null;
        }

        return Branch::query()
            ->whereBelongsTo($this->context->organisation())
            ->where('public_id', $branchUuid)
            ->firstOrFail();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, ?Branch $branch = null): Builder
    {
        return $query
            ->where('organisation_id', $this->context->organisation()->getKey())
            ->when($branch, fn (Builder $query) => $query->where('branch_id', $branch->getKey()));
    }
}
