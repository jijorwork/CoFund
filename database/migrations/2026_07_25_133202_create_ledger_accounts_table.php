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
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->index();
            $table->string('normal_balance');
            $table->string('purpose')->index();
            $table->char('currency_code', 3)->index();
            $table->boolean('allows_direct_posting')->default(true);
            $table->boolean('is_system_account')->default(false);
            $table->string('status')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organisation_id', 'code'], 'ledger_accounts_org_code_unique');
            $table->index(
                ['organisation_id', 'branch_id', 'status'],
                'ledger_accounts_org_branch_status_index',
            );
            $table->index(
                ['organisation_id', 'purpose', 'currency_code', 'status'],
                'ledger_accounts_org_purpose_currency_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
