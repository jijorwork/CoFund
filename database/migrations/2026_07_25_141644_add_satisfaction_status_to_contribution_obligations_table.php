<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contribution_obligations', function (Blueprint $table) {
            $table->renameColumn('status', 'schedule_status');
        });

        Schema::table('contribution_obligations', function (Blueprint $table) {
            $table->string('satisfaction_status')->default('unsatisfied')->index();
        });

        DB::table('contribution_obligations')
            ->where('schedule_status', 'partially_satisfied')
            ->update(['satisfaction_status' => 'partially_satisfied']);
        DB::table('contribution_obligations')
            ->where('schedule_status', 'satisfied')
            ->update(['satisfaction_status' => 'satisfied']);
        DB::table('contribution_obligations')
            ->whereIn('schedule_status', ['partially_satisfied', 'satisfied'])
            ->where('due_date', '<', now()->toDateString())
            ->update(['schedule_status' => 'overdue']);
        DB::table('contribution_obligations')
            ->whereIn('schedule_status', ['partially_satisfied', 'satisfied'])
            ->where('due_date', '=', now()->toDateString())
            ->update(['schedule_status' => 'due']);
        DB::table('contribution_obligations')
            ->whereIn('schedule_status', ['partially_satisfied', 'satisfied'])
            ->where('due_date', '>', now()->toDateString())
            ->update(['schedule_status' => 'upcoming']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contribution_obligations', function (Blueprint $table) {
            $table->dropColumn('satisfaction_status');
            $table->renameColumn('schedule_status', 'status');
        });
    }
};
