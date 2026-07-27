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
        Schema::create('expense_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'er_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'er_branch_fk')->restrictOnDelete();
            $table->string('expense_number');
            $table->foreignId('requester_user_id')->constrained('users', indexName: 'er_requester_fk')->restrictOnDelete();
            $table->foreignId('requested_for_user_id')->nullable()->constrained('users', indexName: 'er_for_user_fk')->restrictOnDelete();
            $table->string('beneficiary_name')->nullable();
            $table->string('beneficiary_type');
            $table->foreignId('expense_category_id')->constrained(indexName: 'er_category_fk')->restrictOnDelete();
            $table->string('title');
            $table->text('purpose');
            $table->unsignedBigInteger('requested_amount_minor');
            $table->unsignedBigInteger('approved_amount_minor')->nullable();
            $table->unsignedBigInteger('paid_amount_minor')->default(0);
            $table->unsignedBigInteger('outstanding_amount_minor')->default(0);
            $table->char('currency_code', 3);
            $table->date('needed_by_date')->nullable();
            $table->date('expense_date')->nullable();
            $table->string('payment_method_preference')->nullable();
            $table->foreignId('requested_source_wallet_id')->nullable()->constrained('wallets', indexName: 'er_source_wallet_fk')->restrictOnDelete();
            $table->string('status')->index();
            $table->string('approval_status')->index();
            $table->json('approval_requirements')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users', indexName: 'er_reviewer_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', indexName: 'er_approver_fk')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users', indexName: 'er_rejector_fk')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users', indexName: 'er_canceller_fk')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users', indexName: 'er_completer_fk')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'expense_number'], 'er_org_number_uq');
            $table->index(['organisation_id', 'branch_id', 'status', 'created_at'], 'er_org_branch_status_created_ix');
            $table->index(['organisation_id', 'requester_user_id', 'status'], 'er_org_requester_status_ix');
            $table->index(['organisation_id', 'expense_category_id', 'needed_by_date'], 'er_org_category_needed_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_requests');
    }
};
