<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welfare_claim_liabilities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_claim_id')->constrained(indexName: 'welfare_liabilities_claim_fk')->restrictOnDelete();
            $table->string('liability_number', 50);
            $table->unsignedBigInteger('approved_benefit_minor');
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('recognised');
            $table->string('active_liability_key')->nullable()->unique();
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions', indexName: 'welfare_liabilities_tx_fk')->restrictOnDelete();
            $table->timestamp('recognised_at');
            $table->foreignId('recognised_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions', indexName: 'welfare_liabilities_reversal_tx_fk')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'liability_number'], 'welfare_liabilities_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'welfare_liabilities_org_idem_uq');
            $table->index(['organisation_id', 'welfare_claim_id', 'status'], 'welfare_liabilities_claim_status_ix');
        });

        Schema::create('welfare_benefit_payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('welfare_claim_id')->constrained(indexName: 'welfare_payments_claim_fk')->restrictOnDelete();
            $table->foreignId('welfare_claim_liability_id')->constrained(indexName: 'welfare_payments_liability_fk')->restrictOnDelete();
            $table->foreignId('welfare_claim_beneficiary_id')->nullable()->constrained(indexName: 'welfare_payments_beneficiary_fk')->restrictOnDelete();
            $table->string('payment_number', 50);
            $table->foreignId('source_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('recipient_type', 40);
            $table->string('recipient_name');
            $table->string('recipient_reference')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('payment_method', 30);
            $table->date('payment_date');
            $table->string('status', 20)->default('posted');
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions', indexName: 'welfare_payments_tx_fk')->restrictOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->foreignId('paid_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions', indexName: 'welfare_payments_reversal_tx_fk')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'payment_number'], 'welfare_payments_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'welfare_payments_org_idem_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'welfare_payments_scope_status_ix');
            $table->index(['welfare_claim_id', 'welfare_claim_beneficiary_id', 'status'], 'welfare_payments_claim_recipient_ix');
        });

        Schema::create('welfare_claim_amendments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_claim_id')->constrained(indexName: 'welfare_amendments_claim_fk')->restrictOnDelete();
            $table->string('amendment_number', 50);
            $table->string('amendment_type', 40);
            $table->text('reason');
            $table->json('original_values');
            $table->json('proposed_values');
            $table->string('status', 20)->default('requested');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'amendment_number'], 'welfare_amendments_org_number_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welfare_claim_amendments');
        Schema::dropIfExists('welfare_benefit_payments');
        Schema::dropIfExists('welfare_claim_liabilities');
    }
};
