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
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'ep_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'ep_branch_fk')->restrictOnDelete();
            $table->foreignId('expense_request_id')->constrained(indexName: 'ep_request_fk')->restrictOnDelete();
            $table->string('payment_number');
            $table->string('payment_type')->index();
            $table->foreignId('source_wallet_id')->constrained('wallets', indexName: 'ep_wallet_fk')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->unique('ep_transaction_uq')->constrained(indexName: 'ep_transaction_fk')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('payment_method')->index();
            $table->date('payment_date')->index();
            $table->string('payee_name');
            $table->string('payee_reference')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('status')->index();
            $table->string('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->foreignId('paid_by_user_id')->constrained('users', indexName: 'ep_payer_fk')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', indexName: 'ep_approver_fk')->restrictOnDelete();
            $table->foreignId('reversed_payment_id')->nullable()->constrained('expense_payments', indexName: 'ep_reversed_fk')->restrictOnDelete();
            $table->foreignId('reversal_payment_id')->nullable()->constrained('expense_payments', indexName: 'ep_reversal_fk')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'payment_number'], 'ep_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'ep_org_idempotency_uq');
            $table->index(['organisation_id', 'branch_id', 'payment_date'], 'ep_org_branch_date_ix');
            $table->index(['organisation_id', 'expense_request_id', 'status'], 'ep_org_request_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
