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
        Schema::create('loan_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('sequence_type', 30);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['organisation_id', 'sequence_type', 'sequence_year'], 'loan_sequences_scope_unique');
        });

        Schema::create('loan_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->string('application_number', 50);
            $table->unsignedBigInteger('requested_principal_minor');
            $table->unsignedSmallInteger('requested_term');
            $table->string('repayment_frequency', 20);
            $table->text('purpose');
            $table->string('purpose_category', 80)->nullable();
            $table->string('income_source')->nullable();
            $table->unsignedBigInteger('declared_monthly_income_minor')->default(0);
            $table->unsignedBigInteger('declared_monthly_expenses_minor')->default(0);
            $table->unsignedBigInteger('existing_debt_minor')->default(0);
            $table->string('preferred_disbursement_method', 30);
            $table->date('application_date');
            $table->string('eligibility_status', 20)->default('not_evaluated');
            $table->string('workflow_status', 40)->default('draft');
            $table->json('eligibility_snapshot')->nullable();
            $table->json('product_terms_snapshot')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['organisation_id', 'application_number'], 'loan_apps_org_number_unique');
            $table->index(['organisation_id', 'branch_id', 'workflow_status'], 'loan_apps_scope_status_index');
            $table->index(['organisation_id', 'member_id', 'workflow_status'], 'loan_apps_member_status_index');
        });

        Schema::create('loan_guarantors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->constrained()->restrictOnDelete();
            $table->foreignId('guarantor_member_id')->constrained('members')->restrictOnDelete();
            $table->unsignedBigInteger('guaranteed_amount_minor');
            $table->unsignedInteger('guarantee_percentage_basis_points')->default(0);
            $table->string('status', 20)->default('invited');
            $table->timestamp('invited_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('consent_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('active_guarantor_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['organisation_id', 'guarantor_member_id', 'status'], 'loan_guarantors_exposure_index');
        });

        Schema::create('loan_collaterals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->constrained()->restrictOnDelete();
            $table->string('collateral_type', 60);
            $table->text('description');
            $table->string('owner_name');
            $table->unsignedBigInteger('estimated_value_minor');
            $table->unsignedBigInteger('accepted_value_minor')->nullable();
            $table->char('currency_code', 3);
            $table->text('identifier_encrypted')->nullable();
            $table->string('identifier_last_four', 4)->nullable();
            $table->string('location')->nullable();
            $table->date('valuation_date')->nullable();
            $table->text('valuation_notes')->nullable();
            $table->string('status', 20)->default('proposed');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'loan_application_id', 'status'], 'loan_collateral_app_status_index');
        });

        Schema::create('loan_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->constrained()->restrictOnDelete();
            $table->string('approval_level', 30);
            $table->string('decision', 20);
            $table->timestamp('requested_at');
            $table->timestamp('decided_at');
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('acting_role_id')->constrained('roles')->restrictOnDelete();
            $table->unsignedBigInteger('approved_principal_minor')->nullable();
            $table->unsignedSmallInteger('approved_term')->nullable();
            $table->unsignedInteger('recommended_rate_basis_points')->nullable();
            $table->text('reason')->nullable();
            $table->string('override_type', 40)->nullable();
            $table->unsignedSmallInteger('sequence_number');
            $table->timestamps();
            $table->unique(['loan_application_id', 'sequence_number'], 'loan_approvals_app_sequence_unique');
            $table->index(['organisation_id', 'loan_application_id', 'approval_level'], 'loan_approvals_lookup_index');
        });

        Schema::create('loan_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('documentable_type', 30);
            $table->unsignedBigInteger('documentable_id');
            $table->string('document_type', 50);
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['organisation_id', 'documentable_type', 'documentable_id'], 'loan_documents_owner_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_documents');
        Schema::dropIfExists('loan_approvals');
        Schema::dropIfExists('loan_collaterals');
        Schema::dropIfExists('loan_guarantors');
        Schema::dropIfExists('loan_applications');
        Schema::dropIfExists('loan_number_sequences');
    }
};
