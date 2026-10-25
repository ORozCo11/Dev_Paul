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
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->decimal('maintenance_cost', 10, 2)->nullable()->after('confirmation_notes');
        });

        Schema::table('ticket_archive_logs', function (Blueprint $table) {
            $table->decimal('maintenance_cost', 10, 2)->nullable()->after('plate_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn('maintenance_cost');
        });

        Schema::table('ticket_archive_logs', function (Blueprint $table) {
            $table->dropColumn('maintenance_cost');
        });
    }
};
