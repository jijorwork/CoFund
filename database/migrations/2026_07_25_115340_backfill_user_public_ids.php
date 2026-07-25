<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('public_id')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['public_id' => (string) Str::uuid7()]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->update(['public_id' => null]);
    }
};
