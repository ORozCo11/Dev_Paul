<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `barangays.name` was globally unique, which blocks two different
     * cities from ever having a same-named barangay (e.g. "Poblacion" is
     * common across many PH municipalities) — a real collision risk now
     * that self-registration can create a new Barangay row per city from
     * whatever free-text name a registrant types.
     */
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropUnique('barangays_name_unique');
            $table->unique(['city_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropUnique(['city_id', 'name']);
            $table->unique('name');
        });
    }
};
