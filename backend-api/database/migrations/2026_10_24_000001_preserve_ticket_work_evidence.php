<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->json('external_cycles')->nullable();
            $table->json('work_history')->nullable();
        });
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('ticket_sub_issue_id')->nullable()->unique('maintenance_ticket_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance_records', function (Blueprint $table) {
            $table->dropUnique('maintenance_ticket_item_unique');
            $table->dropColumn('ticket_sub_issue_id');
        });
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropColumn(['external_cycles', 'work_history']);
        });
    }
};
