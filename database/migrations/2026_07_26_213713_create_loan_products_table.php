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
        Schema::create('loan_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('minimum_amount_minor');
            $table->unsignedBigInteger('maximum_amount_minor');
            $table->unsignedSmallInteger('minimum_term');
            $table->unsignedSmallInteger('maximum_term');
            $table->string('term_unit', 20);
            $table->string('repayment_frequency', 20);
            $table->string('interest_method', 30);
            $table->unsignedInteger('interest_rate_basis_points')->default(0);
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->unsignedSmallInteger('minimum_membership_days')->default(0);
            $table->unsignedInteger('minimum_contribution_count')->default(0);
            $table->unsignedBigInteger('minimum_contribution_value_minor')->default(0);
            $table->unsignedInteger('maximum_loan_to_savings_basis_points')->nullable();
            $table->unsignedSmallInteger('maximum_active_loans')->default(1);
            $table->boolean('blocks_overdue_borrowers')->default(true);
            $table->unsignedSmallInteger('required_guarantor_count')->default(0);
            $table->unsignedInteger('guarantee_limit_basis_points')->default(10000);
            $table->boolean('requires_collateral')->default(false);
            $table->string('processing_fee_type', 20)->default('none');
            $table->unsignedBigInteger('processing_fee_fixed_minor')->default(0);
            $table->unsignedInteger('processing_fee_basis_points')->default(0);
            $table->string('processing_fee_treatment', 30)->default('deducted');
            $table->json('late_fee_rules')->nullable();
            $table->json('early_settlement_rules')->nullable();
            $table->boolean('allows_partial_repayment')->default(true);
            $table->boolean('allows_advance_payment')->default(false);
            $table->unsignedBigInteger('secondary_approval_threshold_minor')->nullable();
            $table->unsignedSmallInteger('non_performing_days')->default(90);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'code'], 'loan_products_org_code_unique');
            $table->index(['organisation_id', 'status'], 'loan_products_org_status_index');
            $table->index(['organisation_id', 'branch_id', 'status'], 'loan_products_scope_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
