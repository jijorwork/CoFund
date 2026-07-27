<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 100);
            $table->string('category', 40);
            $table->boolean('enabled')->default(true);
            $table->boolean('mandatory_override')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('messaging_enabled')->default(false);
            $table->json('recipient_policy')->nullable();
            $table->string('digest_mode', 20)->default('immediate');
            $table->json('quiet_hours_policy')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'event_key'], 'notification_policies_org_event_unique');
            $table->index(['organisation_id', 'category', 'enabled'], 'notification_policies_org_category_index');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('recipient_type', 30);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('event_key', 100)->nullable();
            $table->boolean('in_app_enabled')->nullable();
            $table->boolean('email_enabled')->nullable();
            $table->boolean('sms_enabled')->nullable();
            $table->boolean('messaging_enabled')->nullable();
            $table->string('digest_frequency', 20)->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('timezone', 80)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('preference_scope_key')->unique();
            $table->timestamps();
            $table->index(['organisation_id', 'user_id'], 'notification_preferences_org_user_index');
            $table->index(['organisation_id', 'member_id'], 'notification_preferences_org_member_index');
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('template_key', 120);
            $table->string('event_key', 100);
            $table->string('channel', 20);
            $table->string('locale', 12)->default('en');
            $table->string('name', 150);
            $table->text('subject_template')->nullable();
            $table->text('title_template')->nullable();
            $table->text('body_template');
            $table->text('action_label_template')->nullable();
            $table->text('action_url_template')->nullable();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_system_default')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'template_key', 'channel', 'locale', 'version'], 'notification_templates_scope_version_unique');
            $table->index(['organisation_id', 'event_key', 'channel', 'locale', 'status'], 'notification_templates_resolution_index');
        });

        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('event_key', 100);
            $table->string('category', 40);
            $table->string('priority', 20);
            $table->string('source_type', 60);
            $table->uuid('source_uuid');
            $table->string('recipient_type', 30);
            $table->uuid('recipient_uuid');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('locale', 12);
            $table->string('timezone', 80);
            $table->text('title')->nullable();
            $table->text('body');
            $table->string('action_label')->nullable();
            $table->text('action_url')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->json('policy_snapshot');
            $table->json('context_snapshot')->nullable();
            $table->string('status', 30)->default('pending');
            $table->boolean('mandatory')->default(false);
            $table->boolean('digestible')->default(false);
            $table->string('deduplication_key', 191);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'deduplication_key'], 'notification_dispatches_org_dedupe_unique');
            $table->index(['organisation_id', 'user_id', 'status', 'created_at'], 'notification_dispatches_user_status_index');
            $table->index(['organisation_id', 'event_key', 'created_at'], 'notification_dispatches_event_index');
            $table->index(['organisation_id', 'branch_id', 'created_at'], 'notification_dispatches_branch_index');
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_dispatch_id')->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('destination_masked')->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('suppressed_at')->nullable();
            $table->string('provider_message_reference')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['notification_dispatch_id', 'channel'], 'notification_deliveries_dispatch_channel_unique');
            $table->index(['organisation_id', 'status', 'next_attempt_at'], 'notification_deliveries_retry_index');
        });

        Schema::create('notification_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_delivery_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 30);
            $table->string('provider_request_reference')->nullable();
            $table->string('provider_response_code', 80)->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['notification_delivery_id', 'attempt_number'], 'notification_attempts_delivery_number_unique');
            $table->index(['organisation_id', 'status', 'started_at'], 'notification_attempts_org_status_index');
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_dispatch_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->string('category', 40);
            $table->string('priority', 20);
            $table->text('title');
            $table->text('body');
            $table->string('action_label')->nullable();
            $table->text('action_url')->nullable();
            $table->boolean('mandatory')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'user_id', 'read_at', 'created_at'], 'in_app_notifications_unread_index');
        });

        Schema::create('notification_digests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('recipient_type', 30);
            $table->uuid('recipient_uuid');
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('frequency', 20);
            $table->string('channel', 20);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('scheduled_for');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'recipient_type', 'recipient_uuid', 'frequency', 'channel', 'period_start'], 'notification_digests_period_unique');
            $table->index(['organisation_id', 'status', 'scheduled_for'], 'notification_digests_schedule_index');
        });

        Schema::create('notification_digest_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_digest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_dispatch_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['notification_digest_id', 'sequence_number'], 'notification_digest_items_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digest_items');
        Schema::dropIfExists('notification_digests');
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('notification_delivery_attempts');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_policies');
    }
};
