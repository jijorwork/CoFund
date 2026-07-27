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
            $table->foreignId('collector_collection_session_id')
                ->nullable()
                ->constrained('collector_collection_sessions', indexName: 'cp_collector_session_fk')
                ->restrictOnDelete();
            $table->index(
                ['organisation_id', 'collector_collection_session_id', 'status'],
                'cp_org_session_status_ix',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contribution_payments', function (Blueprint $table) {
            $table->dropIndex('cp_org_session_status_ix');
            $table->dropForeign('cp_collector_session_fk');
            $table->dropColumn('collector_collection_session_id');
        });
    }
};
