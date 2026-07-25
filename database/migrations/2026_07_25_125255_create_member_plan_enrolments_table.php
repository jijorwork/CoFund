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
        Schema::create('member_plan_enrolments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('contribution_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('selected_amount_minor')->nullable();
            $table->char('currency_code', 3);
            $table->date('start_date')->index();
            $table->date('end_date')->nullable();
            $table->date('next_due_date')->nullable()->index();
            $table->string('status')->index();
            $table->string('approval_status')->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('end_reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('active_enrolment_key')->nullable()->unique();
            $table->timestamps();

            $table->index(
                ['organisation_id', 'member_id', 'status'],
                'member_plan_enrolments_org_member_status_index',
            );
            $table->index(
                ['organisation_id', 'contribution_plan_id', 'status'],
                'member_plan_enrolments_org_plan_status_index',
            );
            $table->index(
                ['organisation_id', 'branch_id', 'status'],
                'member_plan_enrolments_org_branch_status_index',
            );
            $table->index(
                ['member_id', 'contribution_plan_id', 'status'],
                'member_plan_enrolments_member_plan_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_plan_enrolments');
    }
};
