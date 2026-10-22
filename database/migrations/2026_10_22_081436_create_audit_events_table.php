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
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('acting_role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('role_assignment_id')->constrained('organisation_user_roles')->restrictOnDelete();
            $table->string('event_key', 120);
            $table->string('subject_type', 80);
            $table->uuid('subject_public_id');
            $table->json('details');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organisation_id', 'created_at']);
            $table->index(['organisation_id', 'subject_type', 'subject_public_id'], 'audit_subject_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
