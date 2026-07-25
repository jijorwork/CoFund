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
        Schema::create('organisation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('value_type');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(
                ['organisation_id', 'key'],
                'organisation_settings_organisation_id_key_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_settings');
    }
};
