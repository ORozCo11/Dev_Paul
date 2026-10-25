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
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->enum('domain', ['Land', 'Water'])->default('Land')->after('category_name');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('hull_material')->nullable()->after('fuel_type');
            $table->string('engine_type')->nullable()->after('hull_material');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropColumn('domain');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['hull_material', 'engine_type']);
        });
    }
};
