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
        Schema::create('expense_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained(indexName: 'ens_org_fk')->cascadeOnDelete();
            $table->string('sequence_type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
            $table->unique(['organisation_id', 'sequence_type', 'year'], 'ens_org_type_year_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_number_sequences');
    }
};
