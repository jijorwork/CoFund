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
        Schema::create('petty_cash_retirement_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'pcri_org_fk')->restrictOnDelete();
            $table->foreignId('petty_cash_retirement_id')->constrained(indexName: 'pcri_retirement_fk')->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained(indexName: 'pcri_category_fk')->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained(indexName: 'pcri_account_fk')->restrictOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount_minor');
            $table->date('expense_date');
            $table->unsignedSmallInteger('sequence_number');
            $table->timestamps();
            $table->unique(['petty_cash_retirement_id', 'sequence_number'], 'pcri_retirement_sequence_uq');
            $table->index(['organisation_id', 'petty_cash_retirement_id'], 'pcri_org_retirement_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_retirement_items');
    }
};
