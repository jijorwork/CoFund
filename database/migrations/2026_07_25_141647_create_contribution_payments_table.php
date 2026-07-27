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
        Schema::create('contribution_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'cp_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'cp_branch_fk')->restrictOnDelete();
            $table->foreignId('member_id')->constrained(indexName: 'cp_member_fk')->restrictOnDelete();
            $table->foreignId('contribution_plan_id')->nullable()->constrained(indexName: 'cp_plan_fk')->restrictOnDelete();
            $table->foreignId('member_plan_enrolment_id')->nullable()->constrained(indexName: 'cp_enrolment_fk')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->constrained(indexName: 'cp_transaction_fk')->restrictOnDelete();
            $table->string('payment_number');
            $table->string('payment_method')->index();
            $table->foreignId('source_wallet_id')->constrained('wallets', indexName: 'cp_source_wallet_fk')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->constrained('wallets', indexName: 'cp_destination_wallet_fk')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('allocated_amount_minor')->default(0);
            $table->unsignedBigInteger('unallocated_amount_minor');
            $table->char('currency_code', 3);
            $table->date('payment_date')->index();
            $table->timestamp('received_at');
            $table->string('status')->index();
            $table->string('external_reference')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_phone', 32)->nullable();
            $table->foreignId('collected_by_user_id')->constrained('users', indexName: 'cp_collector_user_fk')->restrictOnDelete();
            $table->foreignId('collector_wallet_id')->nullable()->constrained('wallets', indexName: 'cp_collector_wallet_fk')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('idempotency_key');
            $table->string('request_fingerprint', 64);
            $table->foreignId('reversed_payment_id')->nullable()->constrained('contribution_payments', indexName: 'cp_reversed_payment_fk')->restrictOnDelete();
            $table->foreignId('reversal_payment_id')->nullable()->constrained('contribution_payments', indexName: 'cp_reversal_payment_fk')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'payment_number'], 'cp_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'cp_org_idempotency_uq');
            $table->unique('financial_transaction_id', 'cp_transaction_uq');
            $table->unique('reversal_payment_id', 'cp_reversal_payment_uq');
            $table->index(['organisation_id', 'member_id', 'payment_date'], 'cp_org_member_date_ix');
            $table->index(['organisation_id', 'branch_id', 'payment_date'], 'cp_org_branch_date_ix');
            $table->index(['organisation_id', 'collected_by_user_id', 'payment_date'], 'cp_org_collector_date_ix');
            $table->index(['organisation_id', 'member_plan_enrolment_id', 'payment_date'], 'cp_org_enrolment_date_ix');
            $table->index(['organisation_id', 'source_wallet_id', 'payment_date'], 'cp_org_source_date_ix');
            $table->index(['organisation_id', 'external_reference'], 'cp_org_external_ref_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_payments');
    }
};
