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
        Schema::create('organisation_access_status_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique('org_access_hist_public_id_unique');
            $table->foreignId('organisation_id')
                ->constrained(indexName: 'org_access_hist_org_fk')
                ->restrictOnDelete();
            $table->foreignId('organisation_user_id')
                ->constrained('organisation_users', indexName: 'org_access_hist_access_fk')
                ->restrictOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('changed_by_user_id')
                ->constrained('users', indexName: 'org_access_hist_changed_by_fk')
                ->restrictOnDelete();
            $table->foreignId('acting_role_id')
                ->constrained('roles', indexName: 'org_access_hist_role_fk')
                ->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['organisation_id', 'organisation_user_id', 'effective_at'],
                'org_access_history_access_effective_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_access_status_histories');
    }
};
