<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welfare_claims', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('claim_number', 50);
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_product_id')->constrained(indexName: 'welfare_claims_product_fk')->restrictOnDelete();
            $table->foreignId('welfare_enrolment_id')->constrained(indexName: 'welfare_claims_enrolment_fk')->restrictOnDelete();
            $table->foreignId('welfare_event_id')->constrained(indexName: 'welfare_claims_event_fk')->restrictOnDelete();
            $table->foreignId('welfare_event_type_id')->constrained(indexName: 'welfare_claims_event_type_fk')->restrictOnDelete();
            $table->string('claimant_type', 30);
            $table->foreignId('claimant_member_id')->nullable()->constrained('members')->restrictOnDelete();
            $table->string('claimant_name')->nullable();
            $table->string('claimant_contact')->nullable();
            $table->unsignedBigInteger('requested_benefit_minor');
            $table->unsignedBigInteger('eligible_benefit_minor')->nullable();
            $table->unsignedBigInteger('approved_benefit_minor')->nullable();
            $table->char('currency_code', 3);
            $table->date('claim_date');
            $table->date('submission_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('eligibility_status', 30)->default('not_evaluated');
            $table->string('verification_status', 30)->default('pending');
            $table->string('approval_status', 30)->default('pending');
            $table->string('payment_status', 30)->default('not_payable');
            $table->json('eligibility_snapshot')->nullable();
            $table->json('benefit_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('liability_recognised_at')->nullable();
            $table->foreignId('liability_transaction_id')->nullable()->constrained('financial_transactions', indexName: 'welfare_claims_liability_tx_fk')->restrictOnDelete();
            $table->unsignedBigInteger('total_paid_minor')->default(0);
            $table->unsignedBigInteger('outstanding_payable_minor')->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'claim_number'], 'welfare_claims_org_number_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'welfare_claims_scope_status_ix');
            $table->index(['organisation_id', 'member_id', 'payment_status'], 'welfare_claims_member_payment_ix');
            $table->index(['welfare_product_id', 'welfare_event_type_id', 'claim_date'], 'welfare_claims_product_event_date_ix');
        });

        Schema::create('welfare_claim_terms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_claim_id')->unique()->constrained(indexName: 'welfare_claim_terms_claim_fk')->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->string('benefit_calculation_type', 40);
            $table->unsignedInteger('benefit_rate_basis_points')->nullable();
            $table->unsignedBigInteger('benefit_base_minor')->nullable();
            $table->unsignedBigInteger('minimum_benefit_minor')->nullable();
            $table->unsignedBigInteger('maximum_benefit_minor')->nullable();
            $table->unsignedBigInteger('requested_benefit_minor');
            $table->unsignedBigInteger('calculated_benefit_minor');
            $table->unsignedBigInteger('approved_benefit_minor')->nullable();
            $table->date('waiting_period_end_date')->nullable();
            $table->date('claim_deadline_date')->nullable();
            $table->json('required_documents')->nullable();
            $table->string('required_approval_level', 30)->nullable();
            $table->json('eligibility_snapshot');
            $table->timestamps();
        });

        Schema::create('welfare_claim_beneficiaries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_claim_id')->constrained(indexName: 'welfare_beneficiaries_claim_fk')->restrictOnDelete();
            $table->string('beneficiary_type', 40);
            $table->foreignId('member_beneficiary_id')->nullable()->constrained('members')->restrictOnDelete();
            $table->foreignId('member_contact_id')->nullable()->constrained('member_contacts')->restrictOnDelete();
            $table->string('name');
            $table->string('relationship_to_member', 80)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('identification_type', 50)->nullable();
            $table->text('identification_reference_encrypted')->nullable();
            $table->string('identification_last_four', 4)->nullable();
            $table->unsignedInteger('allocation_basis_points')->nullable();
            $table->unsignedBigInteger('allocated_benefit_minor')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('payment_eligible')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['organisation_id', 'welfare_claim_id', 'status'], 'welfare_beneficiaries_claim_status_ix');
        });

        Schema::create('welfare_claim_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_claim_id')->constrained(indexName: 'welfare_approvals_claim_fk')->restrictOnDelete();
            $table->string('approval_level', 30);
            $table->string('decision', 20);
            $table->timestamp('requested_at');
            $table->timestamp('decided_at');
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('acting_role_id')->constrained('roles')->restrictOnDelete();
            $table->unsignedBigInteger('recommended_benefit_minor')->nullable();
            $table->unsignedBigInteger('approved_benefit_minor')->nullable();
            $table->text('reason')->nullable();
            $table->string('override_type', 50)->nullable();
            $table->unsignedSmallInteger('sequence_number');
            $table->timestamps();
            $table->unique(['welfare_claim_id', 'sequence_number'], 'welfare_approvals_claim_sequence_uq');
            $table->index(['organisation_id', 'welfare_claim_id', 'approval_level'], 'welfare_approvals_lookup_ix');
        });

        Schema::create('welfare_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_claim_id')->constrained(indexName: 'welfare_documents_claim_fk')->restrictOnDelete();
            $table->string('document_type', 50);
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->string('verification_status', 30)->default('pending');
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'welfare_claim_id', 'document_type'], 'welfare_documents_claim_type_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welfare_documents');
        Schema::dropIfExists('welfare_claim_approvals');
        Schema::dropIfExists('welfare_claim_beneficiaries');
        Schema::dropIfExists('welfare_claim_terms');
        Schema::dropIfExists('welfare_claims');
    }
};
