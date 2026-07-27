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
        Schema::table('contribution_payments', function (Blueprint $table) {
            $table->foreignId('receipt_id')
                ->nullable()
                ->unique('cp_receipt_uq')
                ->constrained('receipts', indexName: 'cp_receipt_fk')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contribution_payments', function (Blueprint $table) {
            $table->dropForeign('cp_receipt_fk');
            $table->dropUnique('cp_receipt_uq');
            $table->dropColumn('receipt_id');
        });
    }
};
