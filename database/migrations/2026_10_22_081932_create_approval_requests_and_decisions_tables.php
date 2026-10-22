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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('workflow_key', 120);
            $table->unsignedInteger('workflow_version');
            $table->string('subject_type');
            $table->uuid('subject_public_id');
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24);
            $table->unsignedSmallInteger('current_step_number');
            $table->json('step_permissions');
            $table->char('active_subject_key', 64)->nullable()->unique();
            $table->timestamp('submitted_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'status', 'created_at'], 'approval_request_queue_index');
            $table->index(['organisation_id', 'subject_public_id'], 'approval_request_subject_index');
        });

        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_request_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('step_number');
            $table->string('outcome', 24);
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('acting_role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('role_assignment_id')->constrained('organisation_user_roles')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['approval_request_id', 'step_number'], 'approval_decision_step_unique');
            $table->index(['organisation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_requests');
    }
};
