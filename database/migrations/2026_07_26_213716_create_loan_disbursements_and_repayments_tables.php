<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_disbursements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('member_loan_wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->constrained()->restrictOnDelete();
            $table->string('disbursement_number', 50);
            $table->string('destination_type', 30);
            $table->string('destination_reference')->nullable();
            $table->unsignedBigInteger('gross_principal_minor');
            $table->unsignedBigInteger('deducted_fee_minor')->default(0);
            $table->unsignedBigInteger('financed_fee_minor')->default(0);
            $table->unsignedBigInteger('net_disbursed_minor');
            $table->char('currency_code', 3);
            $table->string('method', 30);
            $table->date('disbursement_date');
            $table->string('status', 20)->default('posted');
            $table->string('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->foreignId('disbursed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'disbursement_number'], 'loan_disb_org_number_unique');
            $table->unique(['organisation_id', 'idempotency_key'], 'loan_disb_org_idem_unique');
            $table->unique('loan_id', 'loan_disb_one_per_loan_unique');
        });

        Schema::create('loan_repayments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->restrictOnDelete();
            $table->string('repayment_number', 50);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('principal_allocated_minor')->default(0);
            $table->unsignedBigInteger('interest_allocated_minor')->default(0);
            $table->unsignedBigInteger('fee_allocated_minor')->default(0);
            $table->unsignedBigInteger('penalty_allocated_minor')->default(0);
            $table->unsignedBigInteger('unallocated_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('payment_method', 30);
            $table->date('repayment_date');
            $table->string('status', 30)->default('posted');
            $table->foreignId('collector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'repayment_number'], 'loan_repay_org_number_unique');
            $table->unique(['organisation_id', 'idempotency_key'], 'loan_repay_org_idem_unique');
            $table->index(['organisation_id', 'loan_id', 'status'], 'loan_repay_loan_status_index');
        });

        Schema::create('loan_repayment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_repayment_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_repayment_installment_id')
                ->constrained(table: 'loan_repayment_installments', indexName: 'loan_alloc_installment_fk')
                ->restrictOnDelete();
            $table->string('component', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('allocation_order');
            $table->string('status', 20)->default('active');
            $table->foreignId('reversed_allocation_id')->nullable()->constrained('loan_repayment_allocations')->restrictOnDelete();
            $table->timestamps();
            $table->index(['loan_repayment_installment_id', 'component', 'status'], 'loan_alloc_component_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_allocations');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loan_disbursements');
    }
};
