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
        Schema::create('contribution_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('plan_type')->index();
            $table->string('frequency')->index();
            $table->string('amount_type');
            $table->bigInteger('fixed_amount_minor')->nullable();
            $table->bigInteger('minimum_amount_minor')->nullable();
            $table->bigInteger('maximum_amount_minor')->nullable();
            $table->char('currency_code', 3);
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->unsignedTinyInteger('collection_day_of_week')->nullable();
            $table->unsignedTinyInteger('collection_day_of_month')->nullable();
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->boolean('allow_partial_payments')->default(false);
            $table->boolean('allow_advance_payments')->default(false);
            $table->boolean('allow_overpayments')->default(false);
            $table->boolean('requires_approval_to_join')->default(false);
            $table->string('status')->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organisation_id', 'code'], 'contribution_plans_org_code_unique');
            $table->index(
                ['organisation_id', 'branch_id', 'status'],
                'contribution_plans_org_branch_status_index',
            );
            $table->index(
                ['organisation_id', 'group_id', 'status'],
                'contribution_plans_org_group_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_plans');
    }
};
