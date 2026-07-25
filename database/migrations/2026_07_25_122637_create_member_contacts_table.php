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
        Schema::create('member_contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('contact_type');
            $table->string('full_name');
            $table->string('relationship');
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('national_id_type')->nullable();
            $table->text('national_id_number')->nullable();
            $table->decimal('percentage_entitlement', 5, 2)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->string('status');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('primary_scope_key')->nullable()->unique();
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['organisation_id', 'member_id', 'contact_type', 'status'],
                'member_contacts_org_member_type_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_contacts');
    }
};
