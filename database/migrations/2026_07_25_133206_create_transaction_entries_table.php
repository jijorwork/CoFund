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
        Schema::create('transaction_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('entry_type')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('description')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->timestamps();

            $table->unique(
                ['financial_transaction_id', 'sequence_number'],
                'transaction_entries_transaction_sequence_unique',
            );
            $table->index(
                ['organisation_id', 'ledger_account_id'],
                'transaction_entries_org_account_index',
            );
            $table->index(
                ['organisation_id', 'wallet_id'],
                'transaction_entries_org_wallet_index',
            );
            $table->index(
                ['organisation_id', 'member_id'],
                'transaction_entries_org_member_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_entries');
    }
};
