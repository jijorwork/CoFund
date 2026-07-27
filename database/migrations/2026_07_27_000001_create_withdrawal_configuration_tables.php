<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('sequence_type', 30);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['organisation_id', 'sequence_type', 'sequence_year'], 'withdrawal_sequences_scope_uq');
        });

        Schema::create('withdrawal_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('eligible_contribution_product_id')->nullable()->constrained('contribution_plans')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('product_type', 40);
            $table->char('currency_code', 3);
            $table->string('eligible_savings_wallet_type', 40)->default('member_savings');
            $table->unsignedBigInteger('minimum_withdrawal_minor')->nullable();
            $table->unsignedBigInteger('maximum_withdrawal_minor')->nullable();
            $table->unsignedBigInteger('minimum_remaining_balance_minor')->nullable();
            $table->unsignedInteger('minimum_remaining_balance_basis_points')->nullable();
            $table->unsignedInteger('maximum_withdrawal_percentage_basis_points')->nullable();
            $table->unsignedInteger('maximum_withdrawals_per_period')->nullable();
            $table->string('withdrawal_limit_period', 20)->nullable();
            $table->unsignedBigInteger('maximum_total_withdrawal_per_period_minor')->nullable();
            $table->unsignedInteger('minimum_membership_days')->nullable();
            $table->unsignedInteger('minimum_savings_age_days')->nullable();
            $table->unsignedInteger('minimum_contribution_count')->nullable();
            $table->boolean('require_active_contribution_enrolment')->default(false);
            $table->unsignedInteger('notice_period_count')->default(0);
            $table->string('notice_period_unit', 20)->nullable();
            $table->boolean('allow_partial_payout')->default(false);
            $table->boolean('allow_full_balance_withdrawal')->default(false);
            $table->boolean('allow_during_member_exit')->default(false);
            $table->boolean('require_reason')->default(true);
            $table->boolean('require_documents')->default(false);
            $table->string('loan_restriction_policy', 60)->default('retain_required_security_only');
            $table->string('guarantor_restriction_policy', 60)->default('retain_only_existing_guarantor_holds');
            $table->string('welfare_restriction_policy', 60)->default('retain_active_welfare_holds');
            $table->string('fee_type', 20)->default('none');
            $table->unsignedBigInteger('fee_value')->nullable();
            $table->string('fee_treatment', 30)->default('deducted_from_payout');
            $table->boolean('require_primary_approval')->default(true);
            $table->boolean('require_secondary_approval')->default(false);
            $table->unsignedBigInteger('secondary_approval_threshold_minor')->nullable();
            $table->unsignedInteger('payout_deadline_days')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['organisation_id', 'code'], 'withdrawal_products_org_code_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'withdrawal_products_scope_status_ix');
            $table->index(['organisation_id', 'group_id', 'product_type'], 'withdrawal_products_group_type_ix');
        });

        Schema::create('withdrawal_reasons', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_product_id')->nullable()->constrained(indexName: 'withdrawal_reasons_product_fk')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 40);
            $table->boolean('require_explanation')->default(false);
            $table->boolean('require_documentation')->default(false);
            $table->boolean('require_manager_review')->default(false);
            $table->unsignedBigInteger('maximum_withdrawal_minor')->nullable();
            $table->unsignedInteger('maximum_withdrawal_percentage_basis_points')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['organisation_id', 'code'], 'withdrawal_reasons_org_code_uq');
            $table->index(['organisation_id', 'withdrawal_product_id', 'category', 'status'], 'withdrawal_reasons_lookup_ix');
        });

        Schema::create('savings_holds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('hold_number', 50);
            $table->string('hold_type', 40);
            $table->string('source_type', 50);
            $table->uuid('source_uuid')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('active');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('release_reason')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'hold_number'], 'savings_holds_org_number_uq');
            $table->index(['organisation_id', 'member_id', 'wallet_id', 'status'], 'savings_holds_member_wallet_status_ix');
            $table->index(['organisation_id', 'hold_type', 'source_type', 'source_uuid'], 'savings_holds_source_ix');
            $table->index(['starts_at', 'expires_at'], 'savings_holds_effective_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_holds');
        Schema::dropIfExists('withdrawal_reasons');
        Schema::dropIfExists('withdrawal_products');
        Schema::dropIfExists('withdrawal_number_sequences');
    }
};
