<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalization (3NF): `users.barangay_name` is determined by
     * `users.barangay_id` (barangays.name), a transitive dependency that
     * could drift out of sync. Read the name through the barangay relation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('barangay_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('barangay_name')->nullable()->after('city_id');
        });

        DB::table('users')->whereNotNull('barangay_id')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'barangay_name' => DB::table('barangays')->where('id', $user->barangay_id)->value('name'),
            ]);
        });
    }
};
