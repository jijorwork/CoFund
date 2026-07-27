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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->nullableMorphs('walletable');
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('wallet_type')->index();
            $table->string('name');
            $table->string('code');
            $table->char('currency_code', 3)->index();
            $table->string('status')->index();
            $table->boolean('is_system_wallet')->default(false);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('active_owner_key')->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organisation_id', 'code'], 'wallets_org_code_unique');
            $table->index(
                ['organisation_id', 'branch_id', 'status'],
                'wallets_org_branch_status_index',
            );
            $table->index(
                ['organisation_id', 'wallet_type', 'status'],
                'wallets_org_type_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
