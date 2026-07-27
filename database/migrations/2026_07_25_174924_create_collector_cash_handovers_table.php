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
        Schema::create('collector_cash_handovers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'cch_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'cch_branch_fk')->restrictOnDelete();
            $table->foreignId('collector_collection_session_id')->constrained('collector_collection_sessions', indexName: 'cch_session_fk')->restrictOnDelete();
            $table->string('handover_number');
            $table->foreignId('collector_user_id')->constrained('users', indexName: 'cch_collector_fk')->restrictOnDelete();
            $table->foreignId('collector_wallet_id')->constrained('wallets', indexName: 'cch_collector_wallet_fk')->restrictOnDelete();
            $table->foreignId('receiving_user_id')->nullable()->constrained('users', indexName: 'cch_receiver_fk')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->constrained('wallets', indexName: 'cch_destination_fk')->restrictOnDelete();
            $table->unsignedBigInteger('declared_cash_minor');
            $table->unsignedBigInteger('accepted_cash_minor')->nullable();
            $table->unsignedBigInteger('shortage_minor')->default(0);
            $table->unsignedBigInteger('overage_minor')->default(0);
            $table->char('currency_code', 3);
            $table->foreignId('financial_transaction_id')->nullable()->constrained(indexName: 'cch_transaction_fk')->restrictOnDelete();
            $table->string('status')->index();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users', indexName: 'cch_submitter_fk')->restrictOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users', indexName: 'cch_received_by_fk')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', indexName: 'cch_approver_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('idempotency_key');
            $table->string('request_fingerprint', 64);
            $table->foreignId('reversed_handover_id')->nullable()->constrained('collector_cash_handovers', indexName: 'cch_reversed_fk')->restrictOnDelete();
            $table->foreignId('reversal_handover_id')->nullable()->constrained('collector_cash_handovers', indexName: 'cch_reversal_fk')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'handover_number'], 'cch_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'cch_org_idempotency_uq');
            $table->unique('financial_transaction_id', 'cch_transaction_uq');
            $table->unique('reversal_handover_id', 'cch_reversal_uq');
            $table->index(['organisation_id', 'collector_collection_session_id'], 'cch_org_session_ix');
            $table->index(['organisation_id', 'collector_user_id', 'status'], 'cch_org_collector_status_ix');
            $table->index(['organisation_id', 'destination_wallet_id', 'status'], 'cch_org_destination_status_ix');
            $table->index(['organisation_id', 'handed_over_at'], 'cch_org_handed_over_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collector_cash_handovers');
    }
};
