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
        Schema::create('petty_cash_retirements', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'pcr_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'pcr_branch_fk')->restrictOnDelete();
            $table->foreignId('petty_cash_advance_id')->constrained(indexName: 'pcr_advance_fk')->restrictOnDelete();
            $table->string('retirement_number');
            $table->unsignedBigInteger('submitted_amount_minor');
            $table->unsignedBigInteger('accepted_expense_minor')->default(0);
            $table->unsignedBigInteger('returned_cash_minor')->default(0);
            $table->unsignedBigInteger('shortage_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('status')->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->foreignId('submitted_by_user_id')->constrained('users', indexName: 'pcr_submitter_fk')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users', indexName: 'pcr_reviewer_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', indexName: 'pcr_approver_fk')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained(indexName: 'pcr_transaction_fk')->restrictOnDelete();
            $table->string('idempotency_key')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'retirement_number'], 'pcr_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'pcr_org_idempotency_uq');
            $table->index(['organisation_id', 'petty_cash_advance_id', 'status'], 'pcr_org_advance_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_retirements');
    }
};
