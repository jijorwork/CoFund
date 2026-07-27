<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('request_number', 50);
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_product_id')->constrained(indexName: 'withdrawal_requests_product_fk')->restrictOnDelete();
            $table->foreignId('withdrawal_reason_id')->nullable()->constrained(indexName: 'withdrawal_requests_reason_fk')->restrictOnDelete();
            $table->foreignId('savings_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('eligible_amount_minor')->nullable();
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->unsignedBigInteger('fee_minor')->nullable();
            $table->unsignedBigInteger('net_payout_minor')->nullable();
            $table->char('currency_code', 3);
            $table->date('request_date');
            $table->date('required_payout_date')->nullable();
            $table->text('reason_text')->nullable();
            $table->boolean('is_full_balance')->default(false);
            $table->string('status', 30)->default('draft');
            $table->string('eligibility_status', 30)->default('pending');
            $table->string('review_status', 30)->default('pending');
            $table->string('approval_status', 30)->default('pending');
            $table->string('payment_status', 30)->default('not_approved');
            $table->json('eligibility_snapshot')->nullable();
            $table->json('restriction_snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedBigInteger('total_paid_minor')->default(0);
            $table->unsignedBigInteger('outstanding_approved_minor')->default(0);
            $table->foreignId('reservation_hold_id')->nullable()->constrained('savings_holds')->restrictOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'request_number'], 'withdrawal_requests_org_number_uq');
            $table->index(['organisation_id', 'branch_id', 'status'], 'withdrawal_requests_scope_status_ix');
            $table->index(['organisation_id', 'member_id', 'payment_status'], 'withdrawal_requests_member_payment_ix');
            $table->index(['withdrawal_product_id', 'withdrawal_reason_id', 'request_date'], 'withdrawal_requests_product_reason_date_ix');
        });

        Schema::create('withdrawal_request_terms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_request_id')->unique()->constrained(indexName: 'withdrawal_terms_request_fk')->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('eligible_amount_minor');
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->string('fee_type', 20);
            $table->unsignedBigInteger('fee_value')->nullable();
            $table->unsignedBigInteger('fee_minor');
            $table->unsignedBigInteger('net_payout_minor');
            $table->unsignedBigInteger('minimum_remaining_balance_minor');
            $table->unsignedBigInteger('active_holds_minor');
            $table->unsignedBigInteger('loan_restriction_minor');
            $table->unsignedBigInteger('guarantor_restriction_minor');
            $table->unsignedBigInteger('welfare_restriction_minor');
            $table->unsignedBigInteger('pending_withdrawal_reservations_minor');
            $table->date('notice_period_end_date')->nullable();
            $table->date('required_payout_date')->nullable();
            $table->json('eligibility_snapshot');
            $table->json('restriction_snapshot');
            $table->json('calculation_snapshot');
            $table->timestamps();
        });

        Schema::create('withdrawal_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_request_id')->constrained(indexName: 'withdrawal_approvals_request_fk')->restrictOnDelete();
            $table->string('approval_level', 30);
            $table->string('decision', 20);
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('acting_role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->unsignedBigInteger('recommended_amount_minor')->nullable();
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->text('reason')->nullable();
            $table->string('override_type', 50)->nullable();
            $table->unsignedSmallInteger('sequence_number');
            $table->timestamps();
            $table->unique(['withdrawal_request_id', 'sequence_number'], 'withdrawal_approvals_request_sequence_uq');
            $table->index(['organisation_id', 'withdrawal_request_id', 'approval_level'], 'withdrawal_approvals_lookup_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_approvals');
        Schema::dropIfExists('withdrawal_request_terms');
        Schema::dropIfExists('withdrawal_requests');
    }
};
