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
        Schema::create('organisation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique('org_status_hist_public_id_unique');
            $table->foreignId('organisation_id')
                ->constrained(indexName: 'org_status_hist_org_fk')
                ->restrictOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by_user_id')
                ->constrained('users', indexName: 'org_status_hist_actor_fk')
                ->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['organisation_id', 'created_at'],
                'org_status_hist_org_created_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_status_histories');
    }
};
