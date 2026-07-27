# ADR 011: Expense and petty-cash accounting

- Status: Accepted for foundation implementation
- Date: 2026-07-25

Expense approval and cash movement are separate. Approval records are
append-only. Only payment creates a journal, debiting expense accounts and
crediting the selected asset wallet.

The MVP uses one-step reimbursement. Petty-cash disbursement debits staff cash
advance receivable and credits petty cash; it is not an expense. Retirement
debits supported expenses and returned petty cash, and credits the receivable
for the cleared amount. A shortage remains receivable and is never
automatically written off. Corrections use central compensating reversals.

Partial payments allocate deterministically across request lines in sequence
order. Owner review is required for thresholds, evidence timing, reimbursement
payable adoption, permitted source wallets, and shortage resolution.
