<?php

namespace App\Domain\Expenses\Actions;

use App\Domain\Accounting\Enums\AccountCategory;
use App\Domain\Accounting\Enums\LedgerAccountStatus;
use App\Domain\Accounting\Models\LedgerAccount;
use App\Domain\Expenses\Enums\ExpenseApprovalStatus;
use App\Domain\Expenses\Enums\ExpenseRequestStatus;
use App\Domain\Expenses\Enums\ExpenseSequenceType;
use App\Domain\Expenses\Exceptions\InvalidExpenseOperation;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Expenses\Models\ExpenseRequest;
use App\Domain\Expenses\Models\ExpenseRequestItem;
use App\Domain\Expenses\Services\ExpenseNumberGenerator;
use App\Domain\Expenses\Services\ExpenseScope;
use App\Domain\Identity\Enums\FoundationPermission;
use App\Domain\Identity\Services\RoleAuthorization;
use App\Domain\Organisations\Models\Branch;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateExpenseRequest
{
    public function __construct(private TenantContext $context, private RoleAuthorization $authorization, private ExpenseScope $scope, private ExpenseNumberGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: int, unit_amount_minor: int, category?: ExpenseCategory|null, ledger_account?: LedgerAccount|null}>  $items
     */
    public function handle(User $actor, ?Branch $branch, ExpenseCategory $category, array $data, array $items): ExpenseRequest
    {
        $this->authorization->authorize(FoundationPermission::ExpenseRequestsCreate->value);
        $org = $this->context->organisation();
        $this->scope->assertBranch($branch?->getKey());
        if ($category->organisation_id !== $org->getKey() || $category->status->value !== 'active' || $items === []) {
            throw new InvalidExpenseOperation;
        }
        $total = 0;
        foreach ($items as $item) {
            $quantity = $item['quantity'];
            $unit = $item['unit_amount_minor'];
            $account = $item['ledger_account'] ?? null;
            if ($quantity <= 0 || $unit <= 0 || ($account && ($account->organisation_id !== $org->getKey() || $account->category !== AccountCategory::Expense || $account->status !== LedgerAccountStatus::Active))) {
                throw new InvalidExpenseOperation;
            }$total += $quantity * $unit;
        }

        return DB::transaction(function () use ($actor, $branch, $category, $data, $items, $org, $total): ExpenseRequest {
            $request = ExpenseRequest::query()->create([...$data, 'organisation_id' => $org->getKey(), 'branch_id' => $branch?->getKey(), 'expense_number' => $this->numbers->next($org, ExpenseSequenceType::Expense, now($org->timezone)->year), 'requester_user_id' => $actor->getKey(), 'expense_category_id' => $category->getKey(), 'requested_amount_minor' => $total, 'paid_amount_minor' => 0, 'outstanding_amount_minor' => 0, 'currency_code' => $org->currency_code, 'status' => ExpenseRequestStatus::Draft, 'approval_status' => ExpenseApprovalStatus::NotSubmitted]);
            foreach ($items as $index => $item) {
                ExpenseRequestItem::query()->create(['organisation_id' => $org->getKey(), 'expense_request_id' => $request->getKey(), 'expense_category_id' => ($item['category'] ?? null)?->getKey(), 'description' => $item['description'], 'quantity' => $item['quantity'], 'unit_amount_minor' => $item['unit_amount_minor'], 'line_total_minor' => $item['quantity'] * $item['unit_amount_minor'], 'currency_code' => $org->currency_code, 'ledger_account_id' => ($item['ledger_account'] ?? null)?->getKey(), 'sequence_number' => $index + 1]);
            }

            return $request->load(['category', 'items.category', 'items.ledgerAccount', 'requester', 'branch']);
        }, attempts: 3);
    }
}
