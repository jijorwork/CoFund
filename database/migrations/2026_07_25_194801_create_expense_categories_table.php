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
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'ec_org_fk')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories', indexName: 'ec_parent_fk')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_ledger_account_id')->constrained('ledger_accounts', indexName: 'ec_account_fk')->restrictOnDelete();
            $table->boolean('requires_receipt')->default(false);
            $table->boolean('requires_preapproval')->default(true);
            $table->boolean('requires_secondary_approval')->default(false);
            $table->unsignedBigInteger('maximum_without_secondary_approval_minor')->nullable();
            $table->json('allowed_payment_methods')->nullable();
            $table->string('status')->index();
            $table->foreignId('created_by_user_id')->constrained('users', indexName: 'ec_creator_fk')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organisation_id', 'code'], 'ec_org_code_uq');
            $table->index(['organisation_id', 'parent_id', 'status'], 'ec_org_parent_status_ix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
