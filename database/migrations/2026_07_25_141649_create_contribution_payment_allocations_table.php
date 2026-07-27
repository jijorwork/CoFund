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
        Schema::create('contribution_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'cpa_org_fk')->restrictOnDelete();
            $table->foreignId('contribution_payment_id')->constrained(indexName: 'cpa_payment_fk')->restrictOnDelete();
            $table->foreignId('contribution_obligation_id')->constrained(indexName: 'cpa_obligation_fk')->restrictOnDelete();
            $table->foreignId('member_plan_enrolment_id')->constrained(indexName: 'cpa_enrolment_fk')->restrictOnDelete();
            $table->foreignId('member_id')->constrained(indexName: 'cpa_member_fk')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->unsignedInteger('allocation_order');
            $table->string('status')->index();
            $table->foreignId('reversed_allocation_id')->nullable()->constrained('contribution_payment_allocations', indexName: 'cpa_reversed_fk')->restrictOnDelete();
            $table->foreignId('reversal_allocation_id')->nullable()->constrained('contribution_payment_allocations', indexName: 'cpa_reversal_fk')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->foreignId('created_by_user_id')->constrained('users', indexName: 'cpa_creator_fk')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['contribution_payment_id', 'allocation_order'], 'cpa_payment_order_uq');
            $table->unique('reversal_allocation_id', 'cpa_reversal_uq');
            $table->index(['organisation_id', 'contribution_obligation_id', 'status'], 'cpa_org_obligation_status_ix');
            $table->index(['organisation_id', 'member_id', 'status'], 'cpa_org_member_status_ix');
            $table->index(['organisation_id', 'member_plan_enrolment_id', 'status'], 'cpa_org_enrolment_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_payment_allocations');
    }
};
