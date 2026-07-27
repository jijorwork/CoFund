<?php

namespace App\Domain\Withdrawals\Models;

use Database\Factories\Domain\Withdrawals\Models\WithdrawalReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['public_id', 'organisation_id', 'withdrawal_product_id', 'code', 'name', 'description', 'category', 'require_explanation', 'require_documentation', 'require_manager_review', 'maximum_withdrawal_minor', 'maximum_withdrawal_percentage_basis_points', 'status', 'created_by_user_id'])]
class WithdrawalReason extends Model
{
    /** @use HasFactory<WithdrawalReasonFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['require_explanation' => 'boolean', 'require_documentation' => 'boolean', 'require_manager_review' => 'boolean'];
    }
}
