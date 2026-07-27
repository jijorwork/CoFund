<?php

namespace App\Domain\Withdrawals\Models;

use App\Domain\Withdrawals\Enums\WithdrawalProductStatus;
use Database\Factories\Domain\Withdrawals\Models\WithdrawalProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property WithdrawalProductStatus $status */
#[Fillable(['public_id', 'organisation_id', 'branch_id', 'group_id', 'eligible_contribution_product_id', 'code', 'name', 'description', 'product_type', 'currency_code', 'eligible_savings_wallet_type', 'minimum_withdrawal_minor', 'maximum_withdrawal_minor', 'minimum_remaining_balance_minor', 'minimum_remaining_balance_basis_points', 'maximum_withdrawal_percentage_basis_points', 'maximum_withdrawals_per_period', 'withdrawal_limit_period', 'maximum_total_withdrawal_per_period_minor', 'minimum_membership_days', 'minimum_savings_age_days', 'minimum_contribution_count', 'require_active_contribution_enrolment', 'notice_period_count', 'notice_period_unit', 'allow_partial_payout', 'allow_full_balance_withdrawal', 'allow_during_member_exit', 'require_reason', 'require_documents', 'loan_restriction_policy', 'guarantor_restriction_policy', 'welfare_restriction_policy', 'fee_type', 'fee_value', 'fee_treatment', 'require_primary_approval', 'require_secondary_approval', 'secondary_approval_threshold_minor', 'payout_deadline_days', 'status', 'created_by_user_id', 'activated_at', 'suspended_at', 'closed_at'])]
class WithdrawalProduct extends Model
{
    /** @use HasFactory<WithdrawalProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'draft', 'eligible_savings_wallet_type' => 'member_savings'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => WithdrawalProductStatus::class, 'require_active_contribution_enrolment' => 'boolean', 'allow_partial_payout' => 'boolean', 'allow_full_balance_withdrawal' => 'boolean', 'allow_during_member_exit' => 'boolean', 'require_reason' => 'boolean', 'require_documents' => 'boolean', 'require_primary_approval' => 'boolean', 'require_secondary_approval' => 'boolean', 'activated_at' => 'datetime', 'suspended_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
