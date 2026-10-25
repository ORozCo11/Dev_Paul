<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condition checks captured a Remarks field but never displayed it back
 * anywhere in the app (not in the Condition Checks table, not in the
 * ticket-creation prefill, not in any report) — write-only data nobody
 * could ever see again. Observations already covers the same purpose and
 * is the field actually used throughout the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_condition_checks', function (Blueprint $table) {
            $table->text('remarks')->nullable();
        });
    }
};
