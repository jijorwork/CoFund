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
        Schema::create('membership_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(
                ['organisation_id', 'sequence_year'],
                'membership_number_sequences_org_year_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_number_sequences');
    }
};
