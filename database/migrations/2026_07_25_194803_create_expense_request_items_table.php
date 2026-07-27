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
        Schema::create('expense_request_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'eri_org_fk')->restrictOnDelete();
            $table->foreignId('expense_request_id')->constrained(indexName: 'eri_request_fk')->restrictOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained(indexName: 'eri_category_fk')->restrictOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->char('currency_code', 3);
            $table->foreignId('ledger_account_id')->nullable()->constrained(indexName: 'eri_account_fk')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->timestamps();
            $table->unique(['expense_request_id', 'sequence_number'], 'eri_request_sequence_uq');
            $table->index(['organisation_id', 'expense_request_id'], 'eri_org_request_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_request_items');
    }
};
