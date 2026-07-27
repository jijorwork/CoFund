<?php

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Expenses\Enums\ExpenseCategoryStatus;
use App\Domain\Expenses\Models\ExpenseApproval;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Models\ExpenseRequestItem;
use App\Domain\Expenses\Services\DetermineExpenseApprovalRequirements;

test('expense categories and requests preserve tenant ancestry and minor-unit totals', function () {
    $category = ExpenseCategory::factory()->create();
    $request = ExpenseRequest::factory()->create(['organisation_id' => $category->organisation_id, 'expense_category_id' => $category->getKey()]);
    $item = ExpenseRequestItem::factory()->create(['organisation_id' => $request->organisation_id, 'expense_request_id' => $request->getKey()]);
    expect($category->status)->toBe(ExpenseCategoryStatus::Active)
        ->and($category->defaultLedgerAccount->category)->toBe(AccountCategory::Expense)
        ->and($request->category->organisation_id)->toBe($request->organisation_id)
        ->and($item->expenseRequest->organisation_id)->toBe($item->organisation_id)
        ->and($item->line_total_minor)->toBe($item->quantity * $item->unit_amount_minor);
});

test('approval requirements snapshot secondary thresholds using integer minor units', function () {
    $category = ExpenseCategory::factory()->create([
        'requires_secondary_approval' => false,
        'maximum_without_secondary_approval_minor' => 10000,
    ]);
    $service = app(DetermineExpenseApprovalRequirements::class);
    expect($service->determine($category, 10000)['secondary_required'])->toBeFalse()
        ->and($service->determine($category, 10001)['secondary_required'])->toBeTrue();
});

test('approval history is append only', function () {
    $approval = ExpenseApproval::factory()->create();
    expect(fn () => $approval->update(['reason' => 'Changed']))->toThrow(LogicException::class)
        ->and(fn () => $approval->delete())->toThrow(LogicException::class);
});

test('default chart contains petty cash advance and reimbursement control accounts', function () {
    expect(AccountPurpose::PettyCash->value)->toBe('petty_cash')
        ->and(AccountPurpose::StaffCashAdvanceReceivable->value)->toBe('staff_cash_advance_receivable')
        ->and(AccountPurpose::StaffReimbursementPayable->value)->toBe('staff_reimbursement_payable');
});
