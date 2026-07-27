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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'receipts_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'receipts_branch_fk')->restrictOnDelete();
            $table->foreignId('contribution_payment_id')->constrained(indexName: 'receipts_payment_fk')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->constrained(indexName: 'receipts_transaction_fk')->restrictOnDelete();
            $table->string('receipt_number');
            $table->string('receipt_type');
            $table->string('status')->index();
            $table->timestamp('issued_at')->index();
            $table->foreignId('issued_by_user_id')->constrained('users', indexName: 'receipts_issuer_fk')->restrictOnDelete();
            $table->foreignId('member_id')->constrained(indexName: 'receipts_member_fk')->restrictOnDelete();
            $table->string('payer_name')->nullable();
            $table->string('payment_method');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('verification_code', 64)->unique();
            $table->string('verification_hash', 64)->unique();
            $table->foreignId('original_receipt_id')->nullable()->constrained('receipts', indexName: 'receipts_original_fk')->restrictOnDelete();
            $table->foreignId('reversal_receipt_id')->nullable()->constrained('receipts', indexName: 'receipts_reversal_fk')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'receipt_number'], 'receipts_org_number_uq');
            $table->unique('contribution_payment_id', 'receipts_payment_uq');
            $table->unique('reversal_receipt_id', 'receipts_reversal_uq');
            $table->index(['organisation_id', 'member_id', 'issued_at'], 'receipts_org_member_issued_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
