<?php

namespace App\Domain\Collections\Queries;

use App\Domain\Collections\Models\ContributionPayment;
use App\Domain\Contributions\Models\MemberPlanEnrolment;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Domain\Wallets\Models\Wallet;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListContributionPayments
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ContributionPayment>
     */
    public function handle(array $filters, string $sort = '-payment_date'): LengthAwarePaginator
    {
        $query = ContributionPayment::query()
            ->whereBelongsTo($this->context->organisation())
            ->with(['member', 'contributionPlan', 'enrolment', 'receipt', 'collectedBy', 'reversalPayment']);

        if ($this->context->roleAssignment()->branch_id !== null) {
            $query->where('branch_id', $this->context->branch()?->getKey());
        }
        if ($this->context->role()->name === 'collector') {
            $query->where('collected_by_user_id', $this->context->roleAssignment()->user_id);
        }

        $this->filters($query, $filters);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column === 'amount' ? 'amount_minor' : $column, $direction)->orderByDesc('id');

        return $query->paginate(25)->withQueryString();
    }

    /**
     * @param  Builder<ContributionPayment>  $query
     * @param  array<string, mixed>  $filters
     */
    private function filters(Builder $query, array $filters): void
    {
        foreach (['payment_number', 'payment_method', 'status', 'external_reference'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $map = [
            'member_uuid' => [Member::class, 'member_id'],
            'enrolment_uuid' => [MemberPlanEnrolment::class, 'member_plan_enrolment_id'],
            'branch_uuid' => [Branch::class, 'branch_id'],
            'collector_user_uuid' => [User::class, 'collected_by_user_id'],
            'source_wallet_uuid' => [Wallet::class, 'source_wallet_id'],
        ];
        foreach ($map as $filter => [$model, $column]) {
            if (isset($filters[$filter])) {
                $id = $model::query()->where('public_id', $filters[$filter])->value('id');
                $query->where($column, $id ?? 0);
            }
        }
        $query->when($filters['payment_date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($filters['payment_date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('payment_date', '<=', $date));
    }
}
