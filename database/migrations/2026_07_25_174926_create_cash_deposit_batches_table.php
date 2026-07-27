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
        Schema::create('cash_deposit_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'cdb_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'cdb_branch_fk')->restrictOnDelete();
            $table->string('deposit_number');
            $table->foreignId('source_wallet_id')->constrained('wallets', indexName: 'cdb_source_fk')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->constrained('wallets', indexName: 'cdb_destination_fk')->restrictOnDelete();
            $table->string('deposit_method')->index();
            $table->date('deposit_date')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('status')->index();
            $table->string('bank_reference')->nullable();
            $table->string('mobile_money_reference')->nullable();
            $table->string('deposit_slip_number')->nullable();
            $table->foreignId('deposited_by_user_id')->constrained('users', indexName: 'cdb_depositor_fk')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users', indexName: 'cdb_confirmer_fk')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained(indexName: 'cdb_transaction_fk')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('idempotency_key');
            $table->string('request_fingerprint', 64);
            $table->foreignId('reversed_deposit_id')->nullable()->constrained('cash_deposit_batches', indexName: 'cdb_reversed_fk')->restrictOnDelete();
            $table->foreignId('reversal_deposit_id')->nullable()->constrained('cash_deposit_batches', indexName: 'cdb_reversal_fk')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'deposit_number'], 'cdb_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'cdb_org_idempotency_uq');
            $table->unique('financial_transaction_id', 'cdb_transaction_uq');
            $table->unique('reversal_deposit_id', 'cdb_reversal_uq');
            $table->index(['organisation_id', 'branch_id', 'deposit_date'], 'cdb_org_branch_date_ix');
            $table->index(['organisation_id', 'source_wallet_id', 'status'], 'cdb_org_source_status_ix');
            $table->index(['organisation_id', 'destination_wallet_id', 'status'], 'cdb_org_destination_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_deposit_batches');
    }
};
