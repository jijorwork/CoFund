<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welfare_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('sequence_type', 30);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['organisation_id', 'sequence_type', 'sequence_year'], 'welfare_sequences_scope_uq');
        });

        Schema::create('welfare_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('product_type', 30);
            $table->char('currency_code', 3);
            $table->string('eligibility_basis', 40)->default('enrolment');
            $table->unsignedInteger('minimum_membership_days')->default(0);
            $table->unsignedInteger('minimum_contribution_count')->default(0);
            $table->unsignedBigInteger('minimum_contribution_amount_minor')->default(0);
            $table->boolean('require_active_contribution_enrolment')->default(false);
            $table->boolean('require_good_standing')->default(true);
            $table->boolean('block_member_with_arrears')->default(false);
            $table->boolean('block_member_with_overdue_loan')->default(false);
            $table->unsignedInteger('waiting_period_count')->default(0);
            $table->string('waiting_period_unit', 20)->nullable();
            $table->string('benefit_calculation_type', 40);
            $table->unsignedBigInteger('fixed_benefit_minor')->nullable();
            $table->unsignedInteger('benefit_percentage_basis_points')->nullable();
            $table->string('benefit_percentage_base', 40)->nullable();
            $table->unsignedBigInteger('minimum_benefit_minor')->nullable();
            $table->unsignedBigInteger('maximum_benefit_minor')->nullable();
            $table->unsignedInteger('maximum_claims_per_period')->nullable();
            $table->string('claim_limit_period', 20)->nullable();
            $table->unsignedBigInteger('maximum_total_benefit_per_period_minor')->nullable();
            $table->boolean('require_beneficiary')->default(false);
            $table->boolean('allow_member_as_recipient')->default(true);
            $table->boolean('allow_external_recipient')->default(false);
            $table->boolean('require_documents')->default(false);
            $table->boolean('require_verification')->default(false);
            $table->boolean('require_primary_approval')->default(true);
            $table->boolean('require_secondary_approval')->default(false);
            $table->unsignedBigInteger('secondary_approval_threshold_minor')->nullable();
            $table->boolean('partial_payment_allowed')->default(true);
            $table->unsignedInteger('payment_deadline_days')->nullable();
            $table->json('required_document_types')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['organisation_id', 'code'], 'welfare_products_org_code_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'welfare_products_scope_status_ix');
            $table->index(['organisation_id', 'group_id', 'product_type'], 'welfare_products_group_type_ix');
        });

        Schema::create('welfare_event_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_product_id')->nullable()->constrained(indexName: 'welfare_event_types_product_fk')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('event_category', 30);
            $table->unsignedBigInteger('default_benefit_minor')->nullable();
            $table->unsignedBigInteger('minimum_benefit_minor')->nullable();
            $table->unsignedBigInteger('maximum_benefit_minor')->nullable();
            $table->boolean('require_event_date')->default(true);
            $table->boolean('require_location')->default(false);
            $table->boolean('require_related_person')->default(false);
            $table->boolean('require_relationship')->default(false);
            $table->boolean('require_documentation')->default(false);
            $table->boolean('require_verification')->default(false);
            $table->unsignedInteger('claim_submission_deadline_days')->nullable();
            $table->unsignedInteger('maximum_occurrences_per_member')->nullable();
            $table->string('maximum_occurrences_period', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['organisation_id', 'code'], 'welfare_event_types_org_code_uq');
            $table->index(['organisation_id', 'event_category', 'status'], 'welfare_event_types_lookup_ix');
        });

        Schema::create('welfare_enrolments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_product_id')->constrained(indexName: 'welfare_enrolments_product_fk')->restrictOnDelete();
            $table->string('enrolment_number', 50);
            $table->date('enrolled_at');
            $table->date('coverage_start_date');
            $table->date('waiting_period_end_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('active_enrolment_key')->nullable()->unique();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'enrolment_number'], 'welfare_enrolments_org_number_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'welfare_enrolments_scope_status_ix');
            $table->index(['organisation_id', 'member_id', 'welfare_product_id'], 'welfare_enrolments_member_product_ix');
            $table->index(['coverage_start_date', 'waiting_period_end_date'], 'welfare_enrolments_coverage_ix');
        });

        Schema::create('welfare_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('welfare_event_type_id')->constrained(indexName: 'welfare_events_type_fk')->restrictOnDelete();
            $table->string('event_number', 50);
            $table->date('event_date');
            $table->date('reported_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->text('related_person_name_encrypted')->nullable();
            $table->string('related_person_type', 40)->nullable();
            $table->string('relationship_to_member', 80)->nullable();
            $table->string('status', 30)->default('reported');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('verification_notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'event_number'], 'welfare_events_org_number_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'welfare_events_scope_status_ix');
            $table->index(['organisation_id', 'member_id', 'welfare_event_type_id', 'event_date'], 'welfare_events_member_type_date_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('welfare_events');
        Schema::dropIfExists('welfare_enrolments');
        Schema::dropIfExists('welfare_event_types');
        Schema::dropIfExists('welfare_products');
        Schema::dropIfExists('welfare_number_sequences');
    }
};
