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
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('transaction_number');
            $table->string('transaction_type')->index();
            $table->string('status')->index();
            $table->char('currency_code', 3);
            $table->date('transaction_date')->index();
            $table->timestamp('posted_at')->nullable()->index();
            $table->text('description');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('external_reference')->nullable()->index();
            $table->string('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->foreignId('original_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->foreignId('reversal_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['organisation_id', 'transaction_number'],
                'financial_transactions_org_number_unique',
            );
            $table->unique(
                ['organisation_id', 'idempotency_key'],
                'financial_transactions_org_idempotency_unique',
            );
            $table->unique('reversal_transaction_id');
            $table->index(
                ['organisation_id', 'branch_id', 'transaction_date'],
                'financial_transactions_org_branch_date_index',
            );
            $table->index(
                ['organisation_id', 'status', 'transaction_date'],
                'financial_transactions_org_status_date_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
