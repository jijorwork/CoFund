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
        Schema::create('expense_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'ea_org_fk')->restrictOnDelete();
            $table->foreignId('expense_request_id')->constrained(indexName: 'ea_request_fk')->restrictOnDelete();
            $table->string('approval_level');
            $table->string('decision')->index();
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->string('requested_from_role')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users', indexName: 'ea_decider_fk')->restrictOnDelete();
            $table->foreignId('acting_role_id')->nullable()->constrained('roles', indexName: 'ea_role_fk')->restrictOnDelete();
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('sequence_number');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['expense_request_id', 'sequence_number'], 'ea_request_sequence_uq');
            $table->index(['organisation_id', 'expense_request_id', 'decision'], 'ea_org_request_decision_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_approvals');
    }
};
