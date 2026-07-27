<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->foreignId('welfare_benefit_payment_id')->nullable()->after('loan_repayment_id')->constrained(indexName: 'receipts_welfare_payment_fk')->restrictOnDelete();
            $table->unique('welfare_benefit_payment_id', 'receipts_welfare_payment_uq');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropUnique('receipts_welfare_payment_uq');
            $table->dropForeign('receipts_welfare_payment_fk');
            $table->dropColumn('welfare_benefit_payment_id');
        });
    }
};
