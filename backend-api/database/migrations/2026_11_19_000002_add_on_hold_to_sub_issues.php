<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            // "On hold" is a flag on an Under Repair item, not a new status.
            $table->string('on_hold_reason', 600)->nullable();
            $table->timestamp('on_hold_since')->nullable();
            $table->date('on_hold_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropColumn(['on_hold_reason', 'on_hold_since', 'on_hold_until']);
        });
    }
};
