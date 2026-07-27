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
        Schema::create('collector_collection_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'ccs_org_fk')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained(indexName: 'ccs_branch_fk')->restrictOnDelete();
            $table->foreignId('collector_user_id')->constrained('users', indexName: 'ccs_collector_fk')->restrictOnDelete();
            $table->foreignId('collector_wallet_id')->constrained('wallets', indexName: 'ccs_wallet_fk')->restrictOnDelete();
            $table->string('session_number');
            $table->date('session_date');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status')->index();
            $table->unsignedBigInteger('expected_cash_minor')->default(0);
            $table->unsignedBigInteger('expected_non_cash_minor')->default(0);
            $table->unsignedBigInteger('declared_cash_minor')->nullable();
            $table->unsignedBigInteger('shortage_minor')->default(0);
            $table->unsignedBigInteger('overage_minor')->default(0);
            $table->char('currency_code', 3);
            $table->foreignId('opened_by_user_id')->constrained('users', indexName: 'ccs_opener_fk')->restrictOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users', indexName: 'ccs_closer_fk')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users', indexName: 'ccs_submitter_fk')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users', indexName: 'ccs_approver_fk')->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by_user_id')->nullable()->constrained('users', indexName: 'ccs_rejector_fk')->restrictOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('active_wallet_key')->nullable()->unique('ccs_active_wallet_uq');
            $table->timestamps();

            $table->unique(['organisation_id', 'session_number'], 'ccs_org_number_uq');
            $table->index(['organisation_id', 'branch_id', 'session_date'], 'ccs_org_branch_date_ix');
            $table->index(['organisation_id', 'collector_user_id', 'session_date'], 'ccs_org_collector_date_ix');
            $table->index(['organisation_id', 'collector_wallet_id', 'status'], 'ccs_org_wallet_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collector_collection_sessions');
    }
};
