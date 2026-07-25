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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('membership_number');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('preferred_name')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('national_id_type')->nullable();
            $table->text('national_id_number')->nullable();
            $table->string('occupation')->nullable();
            $table->text('residential_address')->nullable();
            $table->text('postal_address')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->date('joined_at');
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->date('exited_at')->nullable();
            $table->text('exit_reason')->nullable();
            $table->date('deceased_at')->nullable();
            $table->string('active_user_link_key')->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['organisation_id', 'membership_number'],
                'members_org_membership_number_unique',
            );
            $table->index(['organisation_id', 'status'], 'members_org_status_index');
            $table->index(
                ['organisation_id', 'branch_id', 'status'],
                'members_org_branch_status_index',
            );
            $table->index(['organisation_id', 'phone'], 'members_org_phone_index');
            $table->index(['organisation_id', 'email'], 'members_org_email_index');
            $table->index(['organisation_id', 'joined_at'], 'members_org_joined_index');
            $table->index(['organisation_id', 'user_id'], 'members_org_user_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
