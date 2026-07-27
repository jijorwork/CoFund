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
        Schema::create('collector_cash_declarations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'ccd_org_fk')->restrictOnDelete();
            $table->foreignId('collector_collection_session_id')->constrained('collector_collection_sessions', indexName: 'ccd_session_fk')->restrictOnDelete();
            $table->string('declaration_number');
            $table->unsignedBigInteger('declared_cash_minor');
            $table->char('currency_code', 3);
            $table->json('denomination_breakdown')->nullable();
            $table->timestamp('declared_at');
            $table->foreignId('declared_by_user_id')->constrained('users', indexName: 'ccd_declarer_fk')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('status')->index();
            $table->foreignId('superseded_by_id')->nullable()->constrained('collector_cash_declarations', indexName: 'ccd_superseded_fk')->restrictOnDelete();
            $table->string('active_session_key')->nullable()->unique('ccd_active_session_uq');
            $table->timestamps();

            $table->unique(['organisation_id', 'declaration_number'], 'ccd_org_number_uq');
            $table->index(['organisation_id', 'collector_collection_session_id', 'status'], 'ccd_org_session_status_ix');
            $table->index(['organisation_id', 'declared_at'], 'ccd_org_declared_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collector_cash_declarations');
    }
};
