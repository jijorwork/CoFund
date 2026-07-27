<?php

use App\Domain\Accounting\Enums\AccountPurpose;
use App\Domain\Loans\Enums\InterestMethod;
use App\Domain\Loans\Enums\ProcessingFeeTreatment;
use App\Domain\Loans\Enums\ProcessingFeeType;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanApproval;
use App\Domain\Loans\Models\LoanProduct;
use App\Domain\Loans\Services\CalculateLoanTerms;
use App\Domain\Loans\Services\GenerateLoanRepaymentSchedule;

test('loan terms use deterministic integer arithmetic for supported interest methods', function (InterestMethod $method, int $expectedInterest) {
    $product = new LoanProduct;
    $product->forceFill(['interest_method' => $method, 'interest_rate_basis_points' => 100, 'processing_fee_type' => ProcessingFeeType::None, 'processing_fee_treatment' => ProcessingFeeTreatment::Deducted]);

    $terms = app(CalculateLoanTerms::class)->calculate($product, 100000, 6);

    expect($terms['contractual_interest_minor'])->toBe($expectedInterest)
        ->and($terms['total_repayable_minor'])->toBe(100000 + $expectedInterest);
})->with([
    'flat' => [InterestMethod::Flat, 6000],
    'reducing balance' => [InterestMethod::ReducingBalance, 3503],
    'zero interest' => [InterestMethod::ZeroInterest, 0],
]);

test('flat schedule reconciles principal interest and total repayable', function () {
    $loan = Loan::factory()->create();
    $rows = app(GenerateLoanRepaymentSchedule::class)->generate($loan->load('offer'));

    expect($rows)->toHaveCount(6)
        ->and(collect($rows)->sum('scheduled_principal_minor'))->toBe($loan->principal_minor)
        ->and(collect($rows)->sum('scheduled_interest_minor'))->toBe($loan->contractual_interest_minor)
        ->and(collect($rows)->sum(fn ($row) => $row->totalScheduledMinor()))->toBe($loan->total_repayable_minor);
});

test('loan approval history and financial records are append only', function () {
    $approval = new LoanApproval;
    $approval->exists = true;

    expect(fn () => $approval->update(['reason' => 'changed']))->toThrow(LogicException::class)
        ->and(AccountPurpose::MemberLoanReceivable->value)->toBe('member_loan_receivable')
        ->and(AccountPurpose::LoanInterestIncome->value)->toBe('loan_interest_income');
});
