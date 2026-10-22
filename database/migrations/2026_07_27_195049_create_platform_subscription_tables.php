<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique('sub_plans_public_id_unique');
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_price_minor');
            $table->unsignedBigInteger('yearly_price_minor');
            $table->char('currency_code', 3)->default('GHS');
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('organisation_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique('org_subs_public_id_unique');
            $table->foreignId('organisation_id')->constrained(indexName: 'org_subs_org_fk')->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained(indexName: 'org_subs_plan_fk')->restrictOnDelete();
            $table->string('billing_interval');
            $table->string('status')->index();
            $table->date('starts_on');
            $table->date('current_period_starts_on');
            $table->date('current_period_ends_on')->index();
            $table->date('grace_ends_on')->nullable()->index();
            $table->date('cancelled_on')->nullable();
            $table->string('active_scope_key')->nullable()->unique();
            $table->foreignId('created_by_user_id')->constrained('users', indexName: 'org_subs_created_by_fk')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'status'], 'org_subs_org_status_index');
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique('sub_invoices_public_id_unique');
            $table->string('invoice_number')->unique();
            $table->foreignId('organisation_id')->constrained(indexName: 'sub_invoices_org_fk')->restrictOnDelete();
            $table->foreignId('organisation_subscription_id')->constrained(indexName: 'sub_invoices_sub_fk')->restrictOnDelete();
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->date('issued_on');
            $table->date('due_on')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('paid_amount_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('status')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'status'], 'sub_invoices_org_status_index');
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique('sub_payments_public_id_unique');
            $table->foreignId('organisation_id')->constrained(indexName: 'sub_payments_org_fk')->restrictOnDelete();
            $table->foreignId('subscription_invoice_id')->constrained(indexName: 'sub_payments_invoice_fk')->restrictOnDelete();
            $table->string('payment_reference')->unique();
            $table->string('payment_method');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->date('paid_on');
            $table->foreignId('recorded_by_user_id')->constrained('users', indexName: 'sub_payments_recorded_by_fk')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('organisation_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
