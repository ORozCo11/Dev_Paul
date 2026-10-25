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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('barangay_id')->constrained('cities')->nullOnDelete();
            // Free-text fallback for residents outside Mandaue City, which is
            // the only city with a real, structured barangay list so far —
            // everyone else types their barangay instead of picking one.
            $table->string('barangay_name')->nullable()->after('city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('barangay_name');
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
