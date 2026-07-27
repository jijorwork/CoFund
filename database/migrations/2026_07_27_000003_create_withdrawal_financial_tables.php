<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_request_id')->constrained(indexName: 'withdrawal_payouts_request_fk')->restrictOnDelete();
            $table->string('payout_number', 50);
            $table->foreignId('member_savings_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('destination_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('recipient_type', 40);
            $table->string('recipient_name');
            $table->string('recipient_reference')->nullable();
            $table->unsignedBigInteger('gross_withdrawal_minor');
            $table->unsignedBigInteger('fee_minor');
            $table->unsignedBigInteger('net_payout_minor');
            $table->char('currency_code', 3);
            $table->string('payment_method', 30);
            $table->date('payout_date');
            $table->string('status', 20)->default('posted');
            $table->foreignId('financial_transaction_id')->constrained('financial_transactions', indexName: 'withdrawal_payouts_tx_fk')->restrictOnDelete();
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->foreignId('paid_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions', indexName: 'withdrawal_payouts_reversal_tx_fk')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'payout_number'], 'withdrawal_payouts_org_number_uq');
            $table->unique(['organisation_id', 'idempotency_key'], 'withdrawal_payouts_org_idem_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'withdrawal_payouts_scope_status_ix');
            $table->index(['withdrawal_request_id', 'payout_date'], 'withdrawal_payouts_request_date_ix');
        });

        Schema::create('withdrawal_request_amendments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_request_id')->constrained(indexName: 'withdrawal_amendments_request_fk')->restrictOnDelete();
            $table->string('amendment_number', 50);
            $table->string('amendment_type', 50);
            $table->text('reason');
            $table->json('original_values');
            $table->json('proposed_values');
            $table->string('status', 20)->default('requested');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'amendment_number'], 'withdrawal_amendments_org_number_uq');
        });

        Schema::table('receipts', function (Blueprint $table): void {
            $table->foreignId('withdrawal_payout_id')->nullable()->unique()->constrained('withdrawal_payouts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('withdrawal_payout_id');
        });
        Schema::dropIfExists('withdrawal_request_amendments');
        Schema::dropIfExists('withdrawal_payouts');
    }
};
