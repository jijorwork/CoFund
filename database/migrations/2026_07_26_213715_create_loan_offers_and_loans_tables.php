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
        Schema::create('loan_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->constrained()->restrictOnDelete();
            $table->string('offer_number', 50);
            $table->unsignedSmallInteger('version');
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedBigInteger('interest_minor');
            $table->unsignedBigInteger('fees_minor');
            $table->unsignedBigInteger('financed_fee_minor')->default(0);
            $table->unsignedBigInteger('deducted_fee_minor')->default(0);
            $table->unsignedBigInteger('total_repayable_minor');
            $table->unsignedSmallInteger('term');
            $table->string('repayment_frequency', 20);
            $table->string('interest_method', 30);
            $table->unsignedInteger('interest_rate_basis_points');
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->date('first_repayment_date');
            $table->date('maturity_date');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('issued');
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'offer_number'], 'loan_offers_org_number_unique');
            $table->unique(['loan_application_id', 'version'], 'loan_offers_app_version_unique');
        });

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('loan_offer_id')->unique()->constrained()->restrictOnDelete();
            $table->string('loan_number', 50);
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedBigInteger('financed_fee_minor')->default(0);
            $table->unsignedBigInteger('contractual_interest_minor')->default(0);
            $table->unsignedBigInteger('total_repayable_minor');
            $table->unsignedBigInteger('disbursed_amount_minor')->default(0);
            $table->char('currency_code', 3);
            $table->date('disbursement_date')->nullable();
            $table->date('first_repayment_date');
            $table->date('maturity_date');
            $table->string('repayment_frequency', 20);
            $table->string('interest_method', 30);
            $table->unsignedInteger('interest_rate_basis_points');
            $table->string('status', 30)->default('pending_disbursement');
            $table->string('performance_status', 30)->default('not_disbursed');
            $table->unsignedBigInteger('outstanding_principal_minor')->default(0);
            $table->unsignedBigInteger('outstanding_interest_minor')->default(0);
            $table->unsignedBigInteger('outstanding_fees_minor')->default(0);
            $table->unsignedBigInteger('outstanding_penalties_minor')->default(0);
            $table->unsignedBigInteger('arrears_minor')->default(0);
            $table->unsignedInteger('days_past_due')->default(0);
            $table->date('next_due_date')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'loan_number'], 'loans_org_number_unique');
            $table->index(['organisation_id', 'branch_id', 'status'], 'loans_scope_status_index');
            $table->index(['organisation_id', 'member_id', 'performance_status'], 'loans_member_performance_index');
        });

        Schema::create('loan_repayment_installments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->date('grace_due_date');
            $table->unsignedBigInteger('opening_principal_minor');
            $table->unsignedBigInteger('scheduled_principal_minor');
            $table->unsignedBigInteger('scheduled_interest_minor');
            $table->unsignedBigInteger('scheduled_fees_minor')->default(0);
            $table->unsignedBigInteger('scheduled_penalties_minor')->default(0);
            $table->unsignedBigInteger('paid_principal_minor')->default(0);
            $table->unsignedBigInteger('paid_interest_minor')->default(0);
            $table->unsignedBigInteger('paid_fees_minor')->default(0);
            $table->unsignedBigInteger('paid_penalties_minor')->default(0);
            $table->string('timing_status', 20)->default('upcoming');
            $table->string('satisfaction_status', 30)->default('unsatisfied');
            $table->string('schedule_status', 20)->default('active');
            $table->timestamps();
            $table->unique(['loan_id', 'sequence_number'], 'loan_installments_sequence_unique');
            $table->index(['organisation_id', 'loan_id', 'grace_due_date'], 'loan_installments_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_installments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_offers');
    }
};
