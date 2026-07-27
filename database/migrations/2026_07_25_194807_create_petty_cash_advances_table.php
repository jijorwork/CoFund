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
        Schema::create('petty_cash_advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'pca_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'pca_branch_fk')->restrictOnDelete();
            $table->string('advance_number');
            $table->foreignId('expense_request_id')->nullable()->constrained(indexName: 'pca_request_fk')->restrictOnDelete();
            $table->foreignId('custodian_user_id')->constrained('users', indexName: 'pca_custodian_fk')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users', indexName: 'pca_recipient_fk')->restrictOnDelete();
            $table->foreignId('petty_cash_wallet_id')->constrained('wallets', indexName: 'pca_petty_wallet_fk')->restrictOnDelete();
            $table->foreignId('funding_wallet_id')->constrained('wallets', indexName: 'pca_funding_wallet_fk')->restrictOnDelete();
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->unsignedBigInteger('disbursed_amount_minor')->default(0);
            $table->unsignedBigInteger('retired_amount_minor')->default(0);
            $table->unsignedBigInteger('refundable_amount_minor')->default(0);
            $table->unsignedBigInteger('shortage_minor')->default(0);
            $table->char('currency_code', 3);
            $table->text('purpose');
            $table->string('status')->index();
            $table->timestamp('requested_at');
            $table->foreignId('requested_by_user_id')->constrained('users', indexName: 'pca_requester_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', indexName: 'pca_approver_fk')->restrictOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->foreignId('disbursed_by_user_id')->nullable()->constrained('users', indexName: 'pca_disburser_fk')->restrictOnDelete();
            $table->date('retirement_due_date')->nullable()->index();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('retired_by_user_id')->nullable()->constrained('users', indexName: 'pca_retirer_fk')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained(indexName: 'pca_transaction_fk')->restrictOnDelete();
            $table->string('idempotency_key')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'advance_number'], 'pca_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'pca_org_idempotency_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'pca_org_branch_status_ix');
            $table->index(['organisation_id', 'recipient_user_id', 'status'], 'pca_org_recipient_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_advances');
    }
};
