<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('acting_role_id')->nullable()->constrained('roles')->cascadeOnUpdate()->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('report_key', 100);
            $table->json('filters');
            $table->json('grouping')->nullable();
            $table->json('sorting')->nullable();
            $table->json('columns')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->boolean('is_favourite')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organisation_id', 'owner_user_id', 'name'], 'saved_reports_owner_name_unique');
            $table->index(['organisation_id', 'report_key'], 'saved_reports_org_report_index');
            $table->index(['organisation_id', 'visibility'], 'saved_reports_org_visibility_index');
            $table->index(['owner_user_id', 'is_favourite'], 'saved_reports_owner_favourite_index');
        });

        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('acting_role_id')->nullable()->constrained('roles')->cascadeOnUpdate()->nullOnDelete();
            $table->string('report_key', 100);
            $table->string('format', 10);
            $table->json('filters');
            $table->json('columns')->nullable();
            $table->json('sorting')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('file_name')->nullable();
            $table->text('storage_path')->nullable();
            $table->unsignedBigInteger('row_count')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'status', 'requested_at'], 'report_exports_org_status_requested_index');
            $table->index(['organisation_id', 'report_key'], 'report_exports_org_report_index');
            $table->index(['requested_by_user_id', 'requested_at'], 'report_exports_user_requested_index');
            $table->index('expires_at');
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('acting_role_id')->nullable()->constrained('roles')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('saved_report_id')->nullable()->constrained('saved_reports')->cascadeOnUpdate()->nullOnDelete();
            $table->string('report_key', 100);
            $table->json('filters');
            $table->string('status', 20);
            $table->unsignedBigInteger('row_count')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'report_key', 'started_at'], 'report_runs_org_report_started_index');
            $table->index(['user_id', 'started_at'], 'report_runs_user_started_index');
            $table->index(['organisation_id', 'status'], 'report_runs_org_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('saved_reports');
    }
};
