<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Distinguishes "a new self-registration still awaiting approval" from
     * "an existing account an Admin later deactivated" — both look like
     * is_active=false, but only the first is a pending registration.
     * null = never approved (pending); once set, stays set even if the
     * account is deactivated again later.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('is_active');
        });

        // Every user that already exists predates this feature — treat them
        // as already-approved so none of them spuriously show up as a
        // pending self-registration.
        DB::table('users')->update(['approved_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
