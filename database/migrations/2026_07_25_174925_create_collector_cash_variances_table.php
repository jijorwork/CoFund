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
        Schema::create('collector_cash_variances', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'ccv_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'ccv_branch_fk')->restrictOnDelete();
            $table->foreignId('collector_collection_session_id')->constrained('collector_collection_sessions', indexName: 'ccv_session_fk')->restrictOnDelete();
            $table->foreignId('collector_cash_handover_id')->nullable()->constrained('collector_cash_handovers', indexName: 'ccv_handover_fk')->restrictOnDelete();
            $table->foreignId('collector_user_id')->constrained('users', indexName: 'ccv_collector_fk')->restrictOnDelete();
            $table->foreignId('collector_wallet_id')->constrained('wallets', indexName: 'ccv_wallet_fk')->restrictOnDelete();
            $table->string('variance_type')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('status')->index();
            $table->text('reason')->nullable();
            $table->timestamp('reported_at');
            $table->foreignId('reported_by_user_id')->constrained('users', indexName: 'ccv_reporter_fk')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users', indexName: 'ccv_resolver_fk')->restrictOnDelete();
            $table->string('resolution_type')->nullable();
            $table->foreignId('resolution_financial_transaction_id')->nullable()->constrained('financial_transactions', indexName: 'ccv_resolution_txn_fk')->restrictOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'branch_id', 'reported_at'], 'ccv_org_branch_reported_ix');
            $table->index(['organisation_id', 'collector_collection_session_id', 'status'], 'ccv_org_session_status_ix');
            $table->index(['organisation_id', 'collector_cash_handover_id'], 'ccv_org_handover_ix');
            $table->index(['organisation_id', 'collector_user_id', 'variance_type', 'status'], 'ccv_org_collector_type_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collector_cash_variances');
    }
};
