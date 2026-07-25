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
        Schema::create('organisation_user_roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->string('active_scope_key')->nullable()->unique();
            $table->timestamps();

            $table->index(
                ['organisation_id', 'user_id', 'status'],
                'organisation_user_roles_org_user_status_index',
            );
            $table->index(
                ['organisation_id', 'branch_id', 'user_id'],
                'organisation_user_roles_org_branch_user_index',
            );
            $table->index(
                ['user_id', 'status', 'starts_at', 'ends_at'],
                'organisation_user_roles_user_validity_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_user_roles');
    }
};
