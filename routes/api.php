<?php

use App\Domain\Loans\Actions\ManageLoanApproval;
use App\Domain\Loans\Actions\ManageLoanCollateral;
use App\Domain\Loans\Actions\ManageLoanGuarantor;
use App\Domain\Loans\Actions\ManageLoanProduct;
use App\Domain\Organisations\Tenancy\TenantContext;
use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\AuthContextController;
use App\Http\Controllers\Api\V1\CashDepositController;
use App\Http\Controllers\Api\V1\CashHandoverController;
use App\Http\Controllers\Api\V1\CashVarianceController;
use App\Http\Controllers\Api\V1\CollectionReportController;
use App\Http\Controllers\Api\V1\CollectorSessionController;
use App\Http\Controllers\Api\V1\ComplaintCaseController;
use App\Http\Controllers\Api\V1\ContributionEnrolmentController;
use App\Http\Controllers\Api\V1\ContributionEnrolmentLifecycleController;
use App\Http\Controllers\Api\V1\ContributionPaymentAllocationController;
use App\Http\Controllers\Api\V1\ContributionPaymentController;
use App\Http\Controllers\Api\V1\ContributionPlanController;
use App\Http\Controllers\Api\V1\ContributionPlanLifecycleController;
use App\Http\Controllers\Api\V1\ContributionScheduleController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseDocumentController;
use App\Http\Controllers\Api\V1\ExpensePaymentController;
use App\Http\Controllers\Api\V1\ExpenseReportController;
use App\Http\Controllers\Api\V1\ExpenseRequestController;
use App\Http\Controllers\Api\V1\FinancialReportController;
use App\Http\Controllers\Api\V1\FinancialTransactionController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\LedgerAccountController;
use App\Http\Controllers\Api\V1\LedgerAccountLifecycleController;
use App\Http\Controllers\Api\V1\LoanApplicationController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\LoanDocumentController;
use App\Http\Controllers\Api\V1\LoanProductController;
use App\Http\Controllers\Api\V1\LoanRepaymentController;
use App\Http\Controllers\Api\V1\LoanReportController;
use App\Http\Controllers\Api\V1\MemberContactController;
use App\Http\Controllers\Api\V1\MemberContributionPaymentController;
use App\Http\Controllers\Api\V1\MemberContributionSummaryController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberDocumentController;
use App\Http\Controllers\Api\V1\MemberLifecycleController;
use App\Http\Controllers\Api\V1\MemberUserLinkController;
use App\Http\Controllers\Api\V1\MemberWalletController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\PettyCashAdvanceController;
use App\Http\Controllers\Api\V1\PettyCashRetirementController;
use App\Http\Controllers\Api\V1\PublicReceiptVerificationController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReportExportController;
use App\Http\Controllers\Api\V1\ReverseFinancialTransactionController;
use App\Http\Controllers\Api\V1\RevokeRoleAssignmentController;
use App\Http\Controllers\Api\V1\RoleAssignmentController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SavedReportController;
use App\Http\Controllers\Api\V1\SavingsHoldController;
use App\Http\Controllers\Api\V1\TrialBalanceController;
use App\Http\Controllers\Api\V1\WalletBalanceController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletLifecycleController;
use App\Http\Controllers\Api\V1\WalletStatementController;
use App\Http\Controllers\Api\V1\WelfareClaimController;
use App\Http\Controllers\Api\V1\WelfareDocumentController;
use App\Http\Controllers\Api\V1\WelfareEnrolmentController;
use App\Http\Controllers\Api\V1\WelfareEventController;
use App\Http\Controllers\Api\V1\WelfareEventTypeController;
use App\Http\Controllers\Api\V1\WelfarePaymentController;
use App\Http\Controllers\Api\V1\WelfareProductController;
use App\Http\Controllers\Api\V1\WelfareReportController;
use App\Http\Controllers\Api\V1\WithdrawableSavingsController;
use App\Http\Controllers\Api\V1\WithdrawalPayoutController;
use App\Http\Controllers\Api\V1\WithdrawalProductController;
use App\Http\Controllers\Api\V1\WithdrawalReasonController;
use App\Http\Controllers\Api\V1\WithdrawalReportController;
use App\Http\Controllers\Api\V1\WithdrawalRequestController;
use App\Http\Requests\Api\V1\LoanDecisionRequest;
use App\Http\Resources\Api\V1\TenantContextResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    Route::get('health', function (Request $request) {
        return ApiResponse::success($request, [
            'status' => 'ok',
            'application' => config('app.name'),
            'api_version' => 'v1',
        ]);
    })->name('health');
    Route::get('public/receipts/verify/{verificationCode}', PublicReceiptVerificationController::class)
        ->middleware('throttle:60,1')
        ->name('public.receipts.verify');

    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('auth/contexts', [AuthContextController::class, 'index'])
            ->name('auth.contexts.index');
        Route::post('auth/context', [AuthContextController::class, 'store'])
            ->name('auth.context.store');

        Route::middleware('tenant.context')->group(function () {
            Route::get('context', function (Request $request, TenantContext $tenantContext) {
                return TenantContextResource::make($tenantContext)->additional([
                    'meta' => ['request_id' => ApiResponse::requestId($request)],
                ]);
            })->name('context');

            Route::middleware('role.context')->group(function () {
                Route::get('complaints', [ComplaintCaseController::class, 'index'])->name('complaints.index');
                Route::post('complaints', [ComplaintCaseController::class, 'store'])->name('complaints.store');
                Route::get('complaints/{complaintUuid}', [ComplaintCaseController::class, 'show'])->name('complaints.show');
                Route::post('complaints/{complaintUuid}/messages', [ComplaintCaseController::class, 'respond'])->name('complaints.messages.store');
                Route::post('complaints/{complaintUuid}/assign', [ComplaintCaseController::class, 'assign'])->name('complaints.assign');
                Route::post('complaints/{complaintUuid}/resolve', [ComplaintCaseController::class, 'resolve'])->name('complaints.resolve');
                Route::post('complaints/{complaintUuid}/close', [ComplaintCaseController::class, 'close'])->name('complaints.close');
                Route::post('complaints/{complaintUuid}/reopen', [ComplaintCaseController::class, 'reopen'])->name('complaints.reopen');
                Route::get('audit-events', [AuditEventController::class, 'index'])->name('audit-events.index');
                Route::get('audit-events/{auditEventUuid}', [AuditEventController::class, 'show'])->name('audit-events.show');
                Route::get('notifications/summary', [NotificationController::class, 'summary'])->name('notifications.summary');
                Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::get('notifications/{notificationUuid}', [NotificationController::class, 'show'])->name('notifications.show');
                Route::post('notifications/{notificationUuid}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
                Route::post('notifications/{notificationUuid}/unread', [NotificationController::class, 'markUnread'])->name('notifications.unread');
                Route::post('notifications/{notificationUuid}/archive', [NotificationController::class, 'archive'])->name('notifications.archive');
                Route::get('notification-preferences', [NotificationPreferenceController::class, 'index'])->name('notification-preferences.index');
                Route::put('notification-preferences', [NotificationPreferenceController::class, 'store'])->name('notification-preferences.store');

                Route::get('dashboard', [DashboardController::class, 'organisation'])->name('dashboard');
                Route::get('branches/{branchUuid}/dashboard', [DashboardController::class, 'branch'])->name('branches.dashboard');
                Route::get('dashboards/{dashboardKey}', [DashboardController::class, 'role'])
                    ->whereIn('dashboardKey', ['treasurer', 'collector', 'loans', 'welfare', 'withdrawals', 'membership', 'audit'])
                    ->name('dashboards.role');

                Route::get('reports/general-ledger', [FinancialReportController::class, 'generalLedger'])->name('reports.general-ledger');
                Route::get('reports/trial-balance', [FinancialReportController::class, 'trialBalance'])->name('reports.trial-balance');
                Route::get('reports/statement-of-financial-position', [FinancialReportController::class, 'financialPosition'])->name('reports.statement-of-financial-position');
                Route::get('reports/income-and-expenditure', [FinancialReportController::class, 'incomeAndExpenditure'])->name('reports.income-and-expenditure');
                Route::get('reports/cash-flow', [FinancialReportController::class, 'cashFlow'])->name('reports.cash-flow');

                Route::apiResource('saved-reports', SavedReportController::class)->parameters(['saved-reports' => 'savedReportUuid']);
                Route::post('saved-reports/{savedReportUuid}/run', [SavedReportController::class, 'run'])->name('saved-reports.run');
                Route::post('saved-reports/{savedReportUuid}/duplicate', [SavedReportController::class, 'duplicate'])->name('saved-reports.duplicate');
                Route::get('report-exports', [ReportExportController::class, 'index'])->name('report-exports.index');
                Route::post('report-exports', [ReportExportController::class, 'store'])->name('report-exports.store');
                Route::get('report-exports/{exportUuid}', [ReportExportController::class, 'show'])->name('report-exports.show');
                Route::get('report-exports/{exportUuid}/download', [ReportExportController::class, 'download'])->name('report-exports.download');
                Route::post('report-exports/{exportUuid}/cancel', [ReportExportController::class, 'cancel'])->name('report-exports.cancel');

                Route::get('roles', RoleController::class)->name('roles.index');
                Route::get('role-assignments', [RoleAssignmentController::class, 'index'])
                    ->name('role-assignments.index');
                Route::post('role-assignments', [RoleAssignmentController::class, 'store'])
                    ->name('role-assignments.store');
                Route::get(
                    'role-assignments/{roleAssignmentUuid}',
                    [RoleAssignmentController::class, 'show'],
                )->name('role-assignments.show');
                Route::post(
                    'role-assignments/{roleAssignmentUuid}/revoke',
                    RevokeRoleAssignmentController::class,
                )->name('role-assignments.revoke');

                Route::get('members', [MemberController::class, 'index'])->name('members.index');
                Route::post('members', [MemberController::class, 'store'])->name('members.store');
                Route::get('members/{memberUuid}', [MemberController::class, 'show'])->name('members.show');
                Route::patch('members/{memberUuid}', [MemberController::class, 'update'])->name('members.update');
                Route::post('members/{memberUuid}/activate', [MemberLifecycleController::class, 'activate'])->name('members.activate');
                Route::post('members/{memberUuid}/suspend', [MemberLifecycleController::class, 'suspend'])->name('members.suspend');
                Route::post('members/{memberUuid}/reinstate', [MemberLifecycleController::class, 'reinstate'])->name('members.reinstate');
                Route::post('members/{memberUuid}/mark-inactive', [MemberLifecycleController::class, 'markInactive'])->name('members.mark-inactive');
                Route::post('members/{memberUuid}/exit', [MemberLifecycleController::class, 'exit'])->name('members.exit');
                Route::post('members/{memberUuid}/mark-deceased', [MemberLifecycleController::class, 'markDeceased'])->name('members.mark-deceased');
                Route::post('members/{memberUuid}/link-user', [MemberUserLinkController::class, 'link'])->name('members.link-user');
                Route::post('members/{memberUuid}/unlink-user', [MemberUserLinkController::class, 'unlink'])->name('members.unlink-user');
                Route::get('members/{memberUuid}/contribution-summary', MemberContributionSummaryController::class)->name('members.contribution-summary');
                Route::get('members/{memberUuid}/wallets', MemberWalletController::class)->name('members.wallets.index');
                Route::get('members/{memberUuid}/contribution-payments', [MemberContributionPaymentController::class, 'index'])->name('members.contribution-payments.index');
                Route::get('members/{memberUuid}/contribution-position', [MemberContributionPaymentController::class, 'position'])->name('members.contribution-position');

                Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
                Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
                Route::get('groups/{groupUuid}', [GroupController::class, 'show'])->name('groups.show');
                Route::patch('groups/{groupUuid}', [GroupController::class, 'update'])->name('groups.update');
                Route::get('groups/{groupUuid}/members', [GroupController::class, 'members'])->name('groups.members.index');
                Route::post('groups/{groupUuid}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
                Route::post('groups/{groupUuid}/members/{memberUuid}/remove', [GroupController::class, 'removeMember'])->name('groups.members.remove');

                Route::get('members/{memberUuid}/contacts', [MemberContactController::class, 'index'])->name('members.contacts.index');
                Route::post('members/{memberUuid}/contacts', [MemberContactController::class, 'store'])->name('members.contacts.store');
                Route::get('members/{memberUuid}/contacts/{contactUuid}', [MemberContactController::class, 'show'])->name('members.contacts.show');
                Route::patch('members/{memberUuid}/contacts/{contactUuid}', [MemberContactController::class, 'update'])->name('members.contacts.update');
                Route::post('members/{memberUuid}/contacts/{contactUuid}/deactivate', [MemberContactController::class, 'deactivate'])->name('members.contacts.deactivate');
                Route::post('members/{memberUuid}/contacts/{contactUuid}/verify', [MemberContactController::class, 'verify'])->name('members.contacts.verify');

                Route::get('members/{memberUuid}/documents', [MemberDocumentController::class, 'index'])->name('members.documents.index');
                Route::post('members/{memberUuid}/documents', [MemberDocumentController::class, 'store'])->name('members.documents.store');
                Route::get('members/{memberUuid}/documents/{documentUuid}', [MemberDocumentController::class, 'show'])->name('members.documents.show');
                Route::get('members/{memberUuid}/documents/{documentUuid}/download', [MemberDocumentController::class, 'download'])->name('members.documents.download');
                Route::post('members/{memberUuid}/documents/{documentUuid}/verify', [MemberDocumentController::class, 'verify'])->name('members.documents.verify');
                Route::post('members/{memberUuid}/documents/{documentUuid}/reject', [MemberDocumentController::class, 'reject'])->name('members.documents.reject');

                Route::get('contribution-plans', [ContributionPlanController::class, 'index'])->name('contribution-plans.index');
                Route::post('contribution-plans', [ContributionPlanController::class, 'store'])->name('contribution-plans.store');
                Route::get('contribution-plans/{contributionPlanUuid}', [ContributionPlanController::class, 'show'])->name('contribution-plans.show');
                Route::patch('contribution-plans/{contributionPlanUuid}', [ContributionPlanController::class, 'update'])->name('contribution-plans.update');
                Route::post('contribution-plans/{contributionPlanUuid}/activate', [ContributionPlanLifecycleController::class, 'activate'])->name('contribution-plans.activate');
                Route::post('contribution-plans/{contributionPlanUuid}/suspend', [ContributionPlanLifecycleController::class, 'suspend'])->name('contribution-plans.suspend');
                Route::post('contribution-plans/{contributionPlanUuid}/reinstate', [ContributionPlanLifecycleController::class, 'reinstate'])->name('contribution-plans.reinstate');
                Route::post('contribution-plans/{contributionPlanUuid}/close', [ContributionPlanLifecycleController::class, 'close'])->name('contribution-plans.close');
                Route::post('contribution-plans/{contributionPlanUuid}/archive', [ContributionPlanLifecycleController::class, 'archive'])->name('contribution-plans.archive');

                Route::get('contribution-enrolments', [ContributionEnrolmentController::class, 'index'])->name('contribution-enrolments.index');
                Route::post('contribution-enrolments', [ContributionEnrolmentController::class, 'store'])->name('contribution-enrolments.store');
                Route::get('contribution-enrolments/{contributionEnrolmentUuid}', [ContributionEnrolmentController::class, 'show'])->name('contribution-enrolments.show');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/approve', [ContributionEnrolmentLifecycleController::class, 'approve'])->name('contribution-enrolments.approve');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/reject', [ContributionEnrolmentLifecycleController::class, 'reject'])->name('contribution-enrolments.reject');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/suspend', [ContributionEnrolmentLifecycleController::class, 'suspend'])->name('contribution-enrolments.suspend');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/reinstate', [ContributionEnrolmentLifecycleController::class, 'reinstate'])->name('contribution-enrolments.reinstate');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/end', [ContributionEnrolmentLifecycleController::class, 'end'])->name('contribution-enrolments.end');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/cancel', [ContributionEnrolmentLifecycleController::class, 'cancel'])->name('contribution-enrolments.cancel');
                Route::get('contribution-enrolments/{contributionEnrolmentUuid}/schedule', [ContributionScheduleController::class, 'index'])->name('contribution-enrolments.schedule.index');
                Route::post('contribution-enrolments/{contributionEnrolmentUuid}/schedule/extend', [ContributionScheduleController::class, 'extend'])->name('contribution-enrolments.schedule.extend');

                Route::get('ledger-accounts', [LedgerAccountController::class, 'index'])->name('ledger-accounts.index');
                Route::post('ledger-accounts', [LedgerAccountController::class, 'store'])->name('ledger-accounts.store');
                Route::get('ledger-accounts/{ledgerAccountUuid}', [LedgerAccountController::class, 'show'])->name('ledger-accounts.show');
                Route::patch('ledger-accounts/{ledgerAccountUuid}', [LedgerAccountController::class, 'update'])->name('ledger-accounts.update');
                Route::post('ledger-accounts/{ledgerAccountUuid}/activate', [LedgerAccountLifecycleController::class, 'activate'])->name('ledger-accounts.activate');
                Route::post('ledger-accounts/{ledgerAccountUuid}/suspend', [LedgerAccountLifecycleController::class, 'suspend'])->name('ledger-accounts.suspend');
                Route::post('ledger-accounts/{ledgerAccountUuid}/reinstate', [LedgerAccountLifecycleController::class, 'reinstate'])->name('ledger-accounts.reinstate');
                Route::post('ledger-accounts/{ledgerAccountUuid}/close', [LedgerAccountLifecycleController::class, 'close'])->name('ledger-accounts.close');

                Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
                Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
                Route::get('wallets/{walletUuid}', [WalletController::class, 'show'])->name('wallets.show');
                Route::get('wallets/{walletUuid}/balance', WalletBalanceController::class)->name('wallets.balance');
                Route::get('wallets/{walletUuid}/statement', WalletStatementController::class)->name('wallets.statement');
                Route::post('wallets/{walletUuid}/suspend', [WalletLifecycleController::class, 'suspend'])->name('wallets.suspend');
                Route::post('wallets/{walletUuid}/reinstate', [WalletLifecycleController::class, 'reinstate'])->name('wallets.reinstate');
                Route::post('wallets/{walletUuid}/close', [WalletLifecycleController::class, 'close'])->name('wallets.close');

                Route::get('transactions', [FinancialTransactionController::class, 'index'])->name('transactions.index');
                Route::post('transactions', [FinancialTransactionController::class, 'store'])->name('transactions.store');
                Route::get('transactions/{transactionUuid}', [FinancialTransactionController::class, 'show'])->name('transactions.show');
                Route::post('transactions/{transactionUuid}/reverse', ReverseFinancialTransactionController::class)->name('transactions.reverse');
                Route::get('accounting/trial-balance', TrialBalanceController::class)->name('accounting.trial-balance');

                Route::get('contribution-payments', [ContributionPaymentController::class, 'index'])->name('contribution-payments.index');
                Route::post('contribution-payments', [ContributionPaymentController::class, 'store'])->name('contribution-payments.store');
                Route::get('contribution-payments/{paymentUuid}', [ContributionPaymentController::class, 'show'])->name('contribution-payments.show');
                Route::post('contribution-payments/{paymentUuid}/reverse', [ContributionPaymentController::class, 'reverse'])->name('contribution-payments.reverse');
                Route::get('contribution-payments/{paymentUuid}/allocations', ContributionPaymentAllocationController::class)->name('contribution-payments.allocations.index');

                Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
                Route::get('receipts/{receiptUuid}', [ReceiptController::class, 'show'])->name('receipts.show');
                Route::get('receipts/{receiptUuid}/print-data', [ReceiptController::class, 'printData'])->name('receipts.print-data');
                Route::post('receipts/{receiptUuid}/reissue', [ReceiptController::class, 'reissue'])->name('receipts.reissue');

                Route::get('collector-sessions', [CollectorSessionController::class, 'index'])->name('collector-sessions.index');
                Route::post('collector-sessions', [CollectorSessionController::class, 'store'])->name('collector-sessions.store');
                Route::get('collector-sessions/{sessionUuid}', [CollectorSessionController::class, 'show'])->name('collector-sessions.show');
                Route::post('collector-sessions/{sessionUuid}/close', [CollectorSessionController::class, 'close'])->name('collector-sessions.close');
                Route::post('collector-sessions/{sessionUuid}/declare-cash', [CollectorSessionController::class, 'declareCash'])->name('collector-sessions.declare-cash');
                Route::post('collector-sessions/{sessionUuid}/submit', [CollectorSessionController::class, 'submit'])->name('collector-sessions.submit');
                Route::post('collector-sessions/{sessionUuid}/approve', [CollectorSessionController::class, 'approve'])->name('collector-sessions.approve');
                Route::post('collector-sessions/{sessionUuid}/reject', [CollectorSessionController::class, 'reject'])->name('collector-sessions.reject');

                Route::get('cash-handovers', [CashHandoverController::class, 'index'])->name('cash-handovers.index');
                Route::post('cash-handovers', [CashHandoverController::class, 'store'])->name('cash-handovers.store');
                Route::get('cash-handovers/{handoverUuid}', [CashHandoverController::class, 'show'])->name('cash-handovers.show');
                Route::post('cash-handovers/{handoverUuid}/submit', [CashHandoverController::class, 'submit'])->name('cash-handovers.submit');
                Route::post('cash-handovers/{handoverUuid}/receive', [CashHandoverController::class, 'receive'])->name('cash-handovers.receive');
                Route::post('cash-handovers/{handoverUuid}/approve', [CashHandoverController::class, 'approve'])->name('cash-handovers.approve');
                Route::post('cash-handovers/{handoverUuid}/reject', [CashHandoverController::class, 'reject'])->name('cash-handovers.reject');
                Route::post('cash-handovers/{handoverUuid}/reverse', [CashHandoverController::class, 'reverse'])->name('cash-handovers.reverse');

                Route::get('cash-variances', [CashVarianceController::class, 'index'])->name('cash-variances.index');
                Route::get('cash-variances/{varianceUuid}', [CashVarianceController::class, 'show'])->name('cash-variances.show');
                Route::post('cash-variances/{varianceUuid}/resolve', [CashVarianceController::class, 'resolve'])->name('cash-variances.resolve');
                Route::post('cash-variances/{varianceUuid}/write-off', [CashVarianceController::class, 'writeOff'])->name('cash-variances.write-off');

                Route::get('cash-deposits', [CashDepositController::class, 'index'])->name('cash-deposits.index');
                Route::post('cash-deposits', [CashDepositController::class, 'store'])->name('cash-deposits.store');
                Route::get('cash-deposits/{depositUuid}', [CashDepositController::class, 'show'])->name('cash-deposits.show');
                Route::post('cash-deposits/{depositUuid}/submit', [CashDepositController::class, 'submit'])->name('cash-deposits.submit');
                Route::post('cash-deposits/{depositUuid}/confirm', [CashDepositController::class, 'confirm'])->name('cash-deposits.confirm');
                Route::post('cash-deposits/{depositUuid}/reject', [CashDepositController::class, 'reject'])->name('cash-deposits.reject');
                Route::post('cash-deposits/{depositUuid}/reverse', [CashDepositController::class, 'reverse'])->name('cash-deposits.reverse');

                Route::get('reports/collector-collections', [CollectionReportController::class, 'collectorCollections'])->name('reports.collector-collections');
                Route::get('reports/cash-handovers', [CollectionReportController::class, 'cashHandovers'])->name('reports.cash-handovers');
                Route::get('reports/cash-variances', [CollectionReportController::class, 'cashVariances'])->name('reports.cash-variances');
                Route::get('reports/cash-deposits', [CollectionReportController::class, 'cashDeposits'])->name('reports.cash-deposits');
                Route::get('reports/cash-position', [CollectionReportController::class, 'cashPosition'])->name('reports.cash-position');

                Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
                Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
                Route::get('expense-categories/{categoryUuid}', [ExpenseCategoryController::class, 'show'])->name('expense-categories.show');
                Route::patch('expense-categories/{categoryUuid}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
                Route::post('expense-categories/{categoryUuid}/suspend', [ExpenseCategoryController::class, 'suspend'])->name('expense-categories.suspend');
                Route::post('expense-categories/{categoryUuid}/reinstate', [ExpenseCategoryController::class, 'reinstate'])->name('expense-categories.reinstate');
                Route::post('expense-categories/{categoryUuid}/archive', [ExpenseCategoryController::class, 'archive'])->name('expense-categories.archive');

                Route::get('expense-requests', [ExpenseRequestController::class, 'index'])->name('expense-requests.index');
                Route::post('expense-requests', [ExpenseRequestController::class, 'store'])->name('expense-requests.store');
                Route::get('expense-requests/{expenseUuid}', [ExpenseRequestController::class, 'show'])->name('expense-requests.show');
                Route::patch('expense-requests/{expenseUuid}', [ExpenseRequestController::class, 'update'])->name('expense-requests.update');
                Route::post('expense-requests/{expenseUuid}/submit', [ExpenseRequestController::class, 'submit'])->name('expense-requests.submit');
                Route::post('expense-requests/{expenseUuid}/review', [ExpenseRequestController::class, 'review'])->name('expense-requests.review');
                Route::post('expense-requests/{expenseUuid}/approve', [ExpenseRequestController::class, 'approve'])->name('expense-requests.approve');
                Route::post('expense-requests/{expenseUuid}/secondary-approve', [ExpenseRequestController::class, 'secondaryApprove'])->name('expense-requests.secondary-approve');
                Route::post('expense-requests/{expenseUuid}/reject', [ExpenseRequestController::class, 'reject'])->name('expense-requests.reject');
                Route::post('expense-requests/{expenseUuid}/cancel', [ExpenseRequestController::class, 'cancel'])->name('expense-requests.cancel');
                Route::post('expense-requests/{expenseUuid}/complete', [ExpenseRequestController::class, 'complete'])->name('expense-requests.complete');

                Route::get('expense-requests/{expenseUuid}/documents', [ExpenseDocumentController::class, 'index'])->name('expense-requests.documents.index');
                Route::post('expense-requests/{expenseUuid}/documents', [ExpenseDocumentController::class, 'store'])->name('expense-requests.documents.store');
                Route::get('expense-requests/{expenseUuid}/documents/{documentUuid}', [ExpenseDocumentController::class, 'show'])->name('expense-requests.documents.show');
                Route::get('expense-requests/{expenseUuid}/documents/{documentUuid}/download', [ExpenseDocumentController::class, 'download'])->name('expense-requests.documents.download');

                Route::get('expense-payments', [ExpensePaymentController::class, 'index'])->name('expense-payments.index');
                Route::post('expense-requests/{expenseUuid}/payments', [ExpensePaymentController::class, 'store'])->name('expense-requests.payments.store');
                Route::get('expense-payments/{paymentUuid}', [ExpensePaymentController::class, 'show'])->name('expense-payments.show');
                Route::post('expense-payments/{paymentUuid}/reverse', [ExpensePaymentController::class, 'reverse'])->name('expense-payments.reverse');

                Route::get('petty-cash-advances', [PettyCashAdvanceController::class, 'index'])->name('petty-cash-advances.index');
                Route::post('petty-cash-advances', [PettyCashAdvanceController::class, 'store'])->name('petty-cash-advances.store');
                Route::get('petty-cash-advances/{advanceUuid}', [PettyCashAdvanceController::class, 'show'])->name('petty-cash-advances.show');
                Route::post('petty-cash-advances/{advanceUuid}/submit', [PettyCashAdvanceController::class, 'submit'])->name('petty-cash-advances.submit');
                Route::post('petty-cash-advances/{advanceUuid}/approve', [PettyCashAdvanceController::class, 'approve'])->name('petty-cash-advances.approve');
                Route::post('petty-cash-advances/{advanceUuid}/reject', [PettyCashAdvanceController::class, 'reject'])->name('petty-cash-advances.reject');
                Route::post('petty-cash-advances/{advanceUuid}/disburse', [PettyCashAdvanceController::class, 'disburse'])->name('petty-cash-advances.disburse');
                Route::post('petty-cash-advances/{advanceUuid}/retirements', [PettyCashAdvanceController::class, 'retire'])->name('petty-cash-advances.retirements.store');
                Route::get('petty-cash-advances/{advanceUuid}/retirements', [PettyCashAdvanceController::class, 'retirements'])->name('petty-cash-advances.retirements.index');
                Route::post('petty-cash-advances/{advanceUuid}/reverse', [PettyCashAdvanceController::class, 'reverse'])->name('petty-cash-advances.reverse');
                Route::post('petty-cash-retirements/{retirementUuid}/submit', [PettyCashRetirementController::class, 'submit'])->name('petty-cash-retirements.submit');
                Route::post('petty-cash-retirements/{retirementUuid}/approve', [PettyCashRetirementController::class, 'approve'])->name('petty-cash-retirements.approve');
                Route::post('petty-cash-retirements/{retirementUuid}/reject', [PettyCashRetirementController::class, 'reject'])->name('petty-cash-retirements.reject');
                Route::get('reports/expenses', [ExpenseReportController::class, 'expenses'])->name('reports.expenses');
                Route::get('reports/expense-payments', [ExpenseReportController::class, 'payments'])->name('reports.expense-payments');
                Route::get('reports/petty-cash', [ExpenseReportController::class, 'pettyCash'])->name('reports.petty-cash');
                Route::get('reports/expense-category-summary', [ExpenseReportController::class, 'categorySummary'])->name('reports.expense-category-summary');
                Route::get('reports/outstanding-advances', [ExpenseReportController::class, 'outstandingAdvances'])->name('reports.outstanding-advances');

                Route::get('loan-products', [LoanProductController::class, 'index'])->name('loan-products.index');
                Route::post('loan-products', [LoanProductController::class, 'store'])->name('loan-products.store');
                Route::get('loan-products/{productUuid}', [LoanProductController::class, 'show'])->name('loan-products.show');
                Route::patch('loan-products/{productUuid}', [LoanProductController::class, 'update'])->name('loan-products.update');
                foreach (['activate' => 'active', 'suspend' => 'suspended', 'reinstate' => 'active', 'close' => 'closed', 'archive' => 'archived'] as $action => $status) {
                    Route::post("loan-products/{productUuid}/{$action}", fn (string $productUuid, LoanProductController $controller, TenantContext $context, ManageLoanProduct $manager) => $controller->transition($productUuid, $status, $context, $manager))->name("loan-products.{$action}");
                }

                Route::get('loan-applications', [LoanApplicationController::class, 'index'])->name('loan-applications.index');
                Route::post('loan-applications', [LoanApplicationController::class, 'store'])->name('loan-applications.store');
                Route::get('loan-applications/{applicationUuid}', [LoanApplicationController::class, 'show'])->name('loan-applications.show');
                Route::patch('loan-applications/{applicationUuid}', [LoanApplicationController::class, 'update'])->name('loan-applications.update');
                Route::get('loan-applications/{applicationUuid}/eligibility', [LoanApplicationController::class, 'eligibility'])->name('loan-applications.eligibility');
                Route::post('loan-applications/{applicationUuid}/submit', [LoanApplicationController::class, 'submit'])->name('loan-applications.submit');
                Route::post('loan-applications/{applicationUuid}/review', fn (LoanDecisionRequest $request, string $applicationUuid, TenantContext $context, ManageLoanApproval $action) => app(LoanApplicationController::class)->decision($request, $applicationUuid, 'review', $context, $action))->name('loan-applications.review');
                Route::post('loan-applications/{applicationUuid}/approve', fn (LoanDecisionRequest $request, string $applicationUuid, TenantContext $context, ManageLoanApproval $action) => app(LoanApplicationController::class)->decision($request, $applicationUuid, 'primary_approval', $context, $action))->name('loan-applications.approve');
                Route::post('loan-applications/{applicationUuid}/secondary-approve', fn (LoanDecisionRequest $request, string $applicationUuid, TenantContext $context, ManageLoanApproval $action) => app(LoanApplicationController::class)->decision($request, $applicationUuid, 'secondary_approval', $context, $action))->name('loan-applications.secondary-approve');
                Route::post('loan-applications/{applicationUuid}/reject', fn (LoanDecisionRequest $request, string $applicationUuid, TenantContext $context, ManageLoanApproval $action) => app(LoanApplicationController::class)->decision($request, $applicationUuid, 'rejected', $context, $action))->name('loan-applications.reject');
                Route::post('loan-applications/{applicationUuid}/cancel', [LoanApplicationController::class, 'cancel'])->name('loan-applications.cancel');
                Route::get('loan-applications/{applicationUuid}/guarantors', [LoanApplicationController::class, 'guarantors'])->name('loan-applications.guarantors.index');
                Route::post('loan-applications/{applicationUuid}/guarantors', [LoanApplicationController::class, 'addGuarantor'])->name('loan-applications.guarantors.store');
                Route::get('loan-guarantors/{guarantorUuid}', [LoanApplicationController::class, 'showGuarantor'])->name('loan-guarantors.show');
                Route::post('loan-guarantors/{guarantorUuid}/accept', fn (string $guarantorUuid, TenantContext $context, ManageLoanGuarantor $action) => app(LoanApplicationController::class)->respondGuarantor($guarantorUuid, 'accepted', $context, $action))->name('loan-guarantors.accept');
                Route::post('loan-guarantors/{guarantorUuid}/decline', fn (string $guarantorUuid, TenantContext $context, ManageLoanGuarantor $action) => app(LoanApplicationController::class)->respondGuarantor($guarantorUuid, 'declined', $context, $action))->name('loan-guarantors.decline');
                Route::post('loan-guarantors/{guarantorUuid}/revoke', [LoanApplicationController::class, 'revokeGuarantor'])->name('loan-guarantors.revoke');
                Route::get('loan-applications/{applicationUuid}/collateral', [LoanApplicationController::class, 'collateral'])->name('loan-applications.collateral.index');
                Route::post('loan-applications/{applicationUuid}/collateral', [LoanApplicationController::class, 'addCollateral'])->name('loan-applications.collateral.store');
                Route::get('loan-collateral/{collateralUuid}', [LoanApplicationController::class, 'showCollateral'])->name('loan-collateral.show');
                Route::patch('loan-collateral/{collateralUuid}', [LoanApplicationController::class, 'updateCollateral'])->name('loan-collateral.update');
                Route::post('loan-collateral/{collateralUuid}/accept', fn (Request $request, string $collateralUuid, TenantContext $context, ManageLoanCollateral $action) => app(LoanApplicationController::class)->reviewCollateral($request, $collateralUuid, 'accepted', $context, $action))->name('loan-collateral.accept');
                Route::post('loan-collateral/{collateralUuid}/reject', fn (Request $request, string $collateralUuid, TenantContext $context, ManageLoanCollateral $action) => app(LoanApplicationController::class)->reviewCollateral($request, $collateralUuid, 'rejected', $context, $action))->name('loan-collateral.reject');
                Route::post('loan-collateral/{collateralUuid}/release', fn (Request $request, string $collateralUuid, TenantContext $context, ManageLoanCollateral $action) => app(LoanApplicationController::class)->reviewCollateral($request, $collateralUuid, 'released', $context, $action))->name('loan-collateral.release');
                Route::get('loan-applications/{applicationUuid}/documents', [LoanDocumentController::class, 'index'])->name('loan-applications.documents.index');
                Route::post('loan-applications/{applicationUuid}/documents', [LoanDocumentController::class, 'store'])->name('loan-applications.documents.store');
                Route::get('loan-applications/{applicationUuid}/documents/{documentUuid}/download', [LoanDocumentController::class, 'download'])->name('loan-applications.documents.download');
                Route::get('loan-applications/{applicationUuid}/offers', [LoanApplicationController::class, 'offers'])->name('loan-applications.offers.index');
                Route::post('loan-applications/{applicationUuid}/offers', [LoanApplicationController::class, 'issueOffer'])->name('loan-applications.offers.store');
                Route::get('loan-offers/{offerUuid}', [LoanApplicationController::class, 'showOffer'])->name('loan-offers.show');
                Route::post('loan-offers/{offerUuid}/accept', [LoanApplicationController::class, 'acceptOffer'])->name('loan-offers.accept');
                Route::post('loan-offers/{offerUuid}/decline', [LoanApplicationController::class, 'declineOffer'])->name('loan-offers.decline');

                Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
                Route::get('loans/{loanUuid}', [LoanController::class, 'show'])->name('loans.show');
                Route::get('loans/{loanUuid}/schedule', [LoanController::class, 'schedule'])->name('loans.schedule');
                Route::get('loans/{loanUuid}/position', [LoanController::class, 'position'])->name('loans.position');
                Route::get('loans/{loanUuid}/settlement-quote', [LoanController::class, 'settlementQuote'])->name('loans.settlement-quote');
                Route::post('loans/{loanUuid}/settle', [LoanController::class, 'settle'])->name('loans.settle');
                Route::post('loans/{loanUuid}/disburse', [LoanController::class, 'disburse'])->name('loans.disburse');
                Route::post('loan-disbursements/{disbursementUuid}/reverse', [LoanController::class, 'reverseDisbursement'])->name('loan-disbursements.reverse');
                Route::get('loan-repayments', [LoanRepaymentController::class, 'index'])->name('loan-repayments.index');
                Route::post('loans/{loanUuid}/repayments', [LoanRepaymentController::class, 'store'])->name('loans.repayments.store');
                Route::get('loan-repayments/{repaymentUuid}', [LoanRepaymentController::class, 'show'])->name('loan-repayments.show');
                Route::get('loan-repayments/{repaymentUuid}/allocations', [LoanRepaymentController::class, 'allocations'])->name('loan-repayments.allocations');
                Route::post('loan-repayments/{repaymentUuid}/reverse', [LoanRepaymentController::class, 'reverse'])->name('loan-repayments.reverse');
                Route::get('reports/loan-portfolio', [LoanReportController::class, 'portfolio'])->name('reports.loan-portfolio');
                Route::get('reports/loan-disbursements', [LoanReportController::class, 'disbursements'])->name('reports.loan-disbursements');
                Route::get('reports/loan-repayments', [LoanReportController::class, 'repayments'])->name('reports.loan-repayments');
                Route::get('reports/loan-arrears', [LoanReportController::class, 'arrears'])->name('reports.loan-arrears');
                Route::get('reports/loan-product-performance', [LoanReportController::class, 'productPerformance'])->name('reports.loan-product-performance');
                Route::get('reports/guarantor-exposure', [LoanReportController::class, 'guarantorExposure'])->name('reports.guarantor-exposure');
                Route::get('members/{memberUuid}/loans', [LoanController::class, 'memberLoans'])->name('members.loans.index');
                Route::get('members/{memberUuid}/loan-summary', [LoanController::class, 'memberSummary'])->name('members.loans.summary');

                Route::apiResource('welfare-products', WelfareProductController::class)->parameters(['welfare-products' => 'productUuid'])->except(['destroy']);
                foreach (['activate', 'suspend', 'reinstate', 'close', 'archive'] as $transition) {
                    Route::post("welfare-products/{productUuid}/{$transition}", [WelfareProductController::class, 'transition'])->defaults('status', $transition)->name("welfare-products.{$transition}");
                }
                Route::apiResource('welfare-event-types', WelfareEventTypeController::class)->parameters(['welfare-event-types' => 'eventTypeUuid'])->except(['destroy']);
                Route::apiResource('welfare-enrolments', WelfareEnrolmentController::class)->parameters(['welfare-enrolments' => 'enrolmentUuid'])->only(['index', 'store', 'show']);
                foreach (['approve', 'activate', 'suspend', 'reinstate', 'terminate'] as $transition) {
                    Route::post("welfare-enrolments/{enrolmentUuid}/{$transition}", [WelfareEnrolmentController::class, 'transition'])->defaults('action', $transition)->name("welfare-enrolments.{$transition}");
                }
                Route::apiResource('welfare-events', WelfareEventController::class)->parameters(['welfare-events' => 'eventUuid'])->except(['destroy']);
                foreach (['verify', 'reject', 'cancel'] as $transition) {
                    Route::post("welfare-events/{eventUuid}/{$transition}", [WelfareEventController::class, 'transition'])->defaults('transition', $transition)->name("welfare-events.{$transition}");
                }
                Route::apiResource('welfare-claims', WelfareClaimController::class)->parameters(['welfare-claims' => 'claimUuid'])->except(['destroy']);
                Route::get('welfare-claims/{claimUuid}/eligibility', [WelfareClaimController::class, 'eligibility'])->name('welfare-claims.eligibility');
                Route::get('welfare-claims/{claimUuid}/position', [WelfareClaimController::class, 'position'])->name('welfare-claims.position');
                Route::post('welfare-claims/{claimUuid}/submit', [WelfareClaimController::class, 'submit'])->name('welfare-claims.submit');
                foreach (['review', 'verify', 'fail-verification', 'reject', 'cancel'] as $transition) {
                    Route::post("welfare-claims/{claimUuid}/{$transition}", [WelfareClaimController::class, 'workflow'])->defaults('transition', $transition)->name("welfare-claims.{$transition}");
                }
                Route::post('welfare-claims/{claimUuid}/approve', [WelfareClaimController::class, 'approve'])->defaults('level', 'primary')->name('welfare-claims.approve');
                Route::post('welfare-claims/{claimUuid}/secondary-approve', [WelfareClaimController::class, 'approve'])->defaults('level', 'secondary')->name('welfare-claims.secondary-approve');
                Route::post('welfare-claims/{claimUuid}/settle', [WelfareClaimController::class, 'settle'])->name('welfare-claims.settle');
                Route::get('welfare-claims/{claimUuid}/beneficiaries', [WelfareClaimController::class, 'beneficiaries'])->name('welfare-claims.beneficiaries.index');
                Route::post('welfare-claims/{claimUuid}/beneficiaries', [WelfareClaimController::class, 'addBeneficiary'])->name('welfare-claims.beneficiaries.store');
                Route::get('welfare-claim-beneficiaries/{beneficiaryUuid}', [WelfareClaimController::class, 'showBeneficiary'])->name('welfare-claim-beneficiaries.show');
                Route::patch('welfare-claim-beneficiaries/{beneficiaryUuid}', [WelfareClaimController::class, 'updateBeneficiary'])->name('welfare-claim-beneficiaries.update');
                Route::get('welfare-claims/{claimUuid}/documents', [WelfareDocumentController::class, 'index'])->name('welfare-claims.documents.index');
                Route::post('welfare-claims/{claimUuid}/documents', [WelfareDocumentController::class, 'store'])->name('welfare-claims.documents.store');
                Route::get('welfare-claims/{claimUuid}/documents/{documentUuid}/download', [WelfareDocumentController::class, 'download'])->name('welfare-claims.documents.download');
                Route::post('welfare-claims/{claimUuid}/documents/{documentUuid}/verify', [WelfareDocumentController::class, 'verify'])->name('welfare-claims.documents.verify');
                Route::get('welfare-claims/{claimUuid}/liability', [WelfarePaymentController::class, 'liability'])->name('welfare-claims.liability.show');
                Route::post('welfare-claims/{claimUuid}/recognise-liability', [WelfarePaymentController::class, 'recognise'])->name('welfare-claims.liability.recognise');
                Route::get('welfare-benefit-payments', [WelfarePaymentController::class, 'index'])->name('welfare-benefit-payments.index');
                Route::post('welfare-claims/{claimUuid}/payments', [WelfarePaymentController::class, 'store'])->name('welfare-claims.payments.store');
                Route::get('welfare-benefit-payments/{paymentUuid}', [WelfarePaymentController::class, 'show'])->name('welfare-benefit-payments.show');
                Route::post('welfare-benefit-payments/{paymentUuid}/reverse', [WelfarePaymentController::class, 'reversePayment'])->name('welfare-benefit-payments.reverse');
                Route::post('welfare-claim-liabilities/{liabilityUuid}/reverse', [WelfarePaymentController::class, 'reverseLiability'])->name('welfare-claim-liabilities.reverse');
                Route::get('members/{memberUuid}/welfare-enrolments', [WelfareReportController::class, 'memberEnrolments'])->name('members.welfare-enrolments.index');
                Route::get('members/{memberUuid}/welfare-claims', [WelfareReportController::class, 'memberClaims'])->name('members.welfare-claims.index');
                Route::get('members/{memberUuid}/welfare-summary', [WelfareReportController::class, 'memberSummary'])->name('members.welfare-summary');
                Route::get('reports/welfare-claims', [WelfareReportController::class, 'claims'])->name('reports.welfare-claims');
                Route::get('reports/welfare-benefits', [WelfareReportController::class, 'benefits'])->name('reports.welfare-benefits');
                Route::get('reports/welfare-outstanding-payables', [WelfareReportController::class, 'outstanding'])->name('reports.welfare-outstanding-payables');
                Route::get('reports/welfare-product-performance', [WelfareReportController::class, 'productPerformance'])->name('reports.welfare-product-performance');
                Route::get('reports/welfare-beneficiary-payments', [WelfareReportController::class, 'beneficiaryPayments'])->name('reports.welfare-beneficiary-payments');
                Route::get('reports/welfare-eligibility-rejections', [WelfareReportController::class, 'eligibilityRejections'])->name('reports.welfare-eligibility-rejections');

                Route::apiResource('withdrawal-products', WithdrawalProductController::class)->parameters(['withdrawal-products' => 'productUuid'])->except(['destroy']);
                foreach (['activate', 'suspend', 'reinstate', 'close', 'archive'] as $transition) {
                    Route::post("withdrawal-products/{productUuid}/{$transition}", [WithdrawalProductController::class, 'transition'])->defaults('transition', $transition)->name("withdrawal-products.{$transition}");
                }
                Route::apiResource('withdrawal-reasons', WithdrawalReasonController::class)->parameters(['withdrawal-reasons' => 'reasonUuid'])->except(['destroy']);
                Route::get('savings-holds', [SavingsHoldController::class, 'index'])->name('savings-holds.index');
                Route::post('savings-holds', [SavingsHoldController::class, 'store'])->name('savings-holds.store');
                Route::get('savings-holds/{holdUuid}', [SavingsHoldController::class, 'show'])->name('savings-holds.show');
                Route::post('savings-holds/{holdUuid}/release', [SavingsHoldController::class, 'release'])->name('savings-holds.release');
                foreach (['approve', 'cancel'] as $transition) {
                    Route::post("savings-holds/{holdUuid}/{$transition}", [SavingsHoldController::class, 'transition'])->defaults('transition', $transition)->name("savings-holds.{$transition}");
                }
                Route::get('members/{memberUuid}/withdrawable-savings', WithdrawableSavingsController::class)->name('members.withdrawable-savings');
                Route::get('withdrawal-requests', [WithdrawalRequestController::class, 'index'])->name('withdrawal-requests.index');
                Route::post('withdrawal-requests', [WithdrawalRequestController::class, 'store'])->name('withdrawal-requests.store');
                Route::get('withdrawal-requests/{requestUuid}', [WithdrawalRequestController::class, 'show'])->name('withdrawal-requests.show');
                Route::get('withdrawal-requests/{requestUuid}/eligibility', [WithdrawalRequestController::class, 'eligibility'])->name('withdrawal-requests.eligibility');
                Route::get('withdrawal-requests/{requestUuid}/position', [WithdrawalRequestController::class, 'position'])->name('withdrawal-requests.position');
                foreach (['submit', 'review', 'approve', 'secondary-approve', 'reject', 'cancel', 'settle'] as $transition) {
                    Route::post("withdrawal-requests/{requestUuid}/{$transition}", [WithdrawalRequestController::class, 'workflow'])->defaults('transition', $transition)->name("withdrawal-requests.{$transition}");
                }
                Route::get('withdrawal-payouts', [WithdrawalPayoutController::class, 'index'])->name('withdrawal-payouts.index');
                Route::post('withdrawal-requests/{requestUuid}/payouts', [WithdrawalPayoutController::class, 'store'])->name('withdrawal-requests.payouts.store');
                Route::get('withdrawal-payouts/{payoutUuid}', [WithdrawalPayoutController::class, 'show'])->name('withdrawal-payouts.show');
                Route::post('withdrawal-payouts/{payoutUuid}/reverse', [WithdrawalPayoutController::class, 'reverse'])->name('withdrawal-payouts.reverse');
                Route::get('members/{memberUuid}/withdrawal-requests', [WithdrawalReportController::class, 'memberRequests'])->name('members.withdrawal-requests.index');
                Route::get('members/{memberUuid}/withdrawal-summary', [WithdrawalReportController::class, 'memberSummary'])->name('members.withdrawal-summary');
                Route::get('reports/withdrawal-requests', [WithdrawalReportController::class, 'requests'])->name('reports.withdrawal-requests');
                Route::get('reports/withdrawal-payouts', [WithdrawalReportController::class, 'payouts'])->name('reports.withdrawal-payouts');
                Route::get('reports/withdrawal-fees', [WithdrawalReportController::class, 'fees'])->name('reports.withdrawal-fees');
                Route::get('reports/outstanding-withdrawals', [WithdrawalReportController::class, 'outstanding'])->name('reports.outstanding-withdrawals');
                Route::get('reports/savings-holds', [WithdrawalReportController::class, 'holds'])->name('reports.savings-holds');
                Route::get('reports/withdrawal-rejections', [WithdrawalReportController::class, 'rejections'])->name('reports.withdrawal-rejections');
                Route::get('reports/withdrawal-restrictions', [WithdrawalReportController::class, 'restrictions'])->name('reports.withdrawal-restrictions');
                Route::get('reports/withdrawal-product-performance', [WithdrawalReportController::class, 'productPerformance'])->name('reports.withdrawal-product-performance');
            });
        });
    });
});
