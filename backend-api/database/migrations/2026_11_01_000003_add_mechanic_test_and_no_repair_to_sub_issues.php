<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            // The Maintenance Personnel's own test of the vehicle after the work (or after it
            // came back from an outside shop). The Custodian only verifies, and does not test.
            $table->timestamp('mechanic_tested_at')->nullable();
            $table->foreignId('mechanic_tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('mechanic_test_notes')->nullable();
            // Set when the Maintenance Personnel inspected the vehicle and found nothing to repair.
            $table->boolean('no_repair_needed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mechanic_tested_by');
            $table->dropColumn(['mechanic_tested_at', 'mechanic_test_notes', 'no_repair_needed']);
        });
    }
};
