<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Expenses\Models\ExpenseCategory;

final class DetermineExpenseApprovalRequirements
{
    /** @return array{review_required:bool,primary_required:bool,secondary_required:bool,distinct_actors:bool} */
    public function determine(ExpenseCategory $category, int $amountMinor): array
    {
        $secondary = $category->requires_secondary_approval || ($category->maximum_without_secondary_approval_minor !== null && $amountMinor > $category->maximum_without_secondary_approval_minor);

        return ['review_required' => $category->requires_preapproval, 'primary_required' => true, 'secondary_required' => $secondary, 'distinct_actors' => true];
    }
}
