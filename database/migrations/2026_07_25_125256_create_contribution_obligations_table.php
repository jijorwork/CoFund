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
        Schema::create('contribution_obligations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('contribution_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_plan_enrolment_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->bigInteger('expected_amount_minor')->nullable();
            $table->char('currency_code', 3);
            $table->string('status')->index();
            $table->date('grace_due_date')->nullable();
            $table->bigInteger('satisfied_amount_minor')->default(0);
            $table->bigInteger('outstanding_amount_minor')->default(0);
            $table->timestamp('satisfied_at')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(
                ['member_plan_enrolment_id', 'sequence_number'],
                'contribution_obligations_enrolment_sequence_unique',
            );
            $table->unique(
                ['member_plan_enrolment_id', 'due_date'],
                'contribution_obligations_enrolment_due_unique',
            );
            $table->index(
                ['organisation_id', 'due_date', 'status'],
                'contribution_obligations_org_due_status_index',
            );
            $table->index(
                ['organisation_id', 'member_id', 'due_date'],
                'contribution_obligations_org_member_due_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_obligations');
    }
};
