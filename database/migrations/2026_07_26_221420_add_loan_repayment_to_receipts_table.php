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
        Schema::table('receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('contribution_payment_id')->nullable()->change();
            $table->foreignId('loan_repayment_id')
                ->nullable()
                ->after('contribution_payment_id')
                ->constrained(indexName: 'receipts_loan_repayment_fk')
                ->restrictOnDelete();
            $table->unique('loan_repayment_id', 'receipts_loan_repayment_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique('receipts_loan_repayment_uq');
            $table->dropForeign('receipts_loan_repayment_fk');
            $table->dropColumn('loan_repayment_id');
            $table->unsignedBigInteger('contribution_payment_id')->nullable(false)->change();
        });
    }
};
