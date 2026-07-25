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
        Schema::create('group_memberships', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->date('joined_at');
            $table->date('left_at')->nullable();
            $table->string('status');
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('active_membership_key')->nullable()->unique();
            $table->timestamps();

            $table->index(
                ['organisation_id', 'group_id', 'status'],
                'group_memberships_org_group_status_index',
            );
            $table->index(
                ['organisation_id', 'member_id', 'status'],
                'group_memberships_org_member_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_memberships');
    }
};
