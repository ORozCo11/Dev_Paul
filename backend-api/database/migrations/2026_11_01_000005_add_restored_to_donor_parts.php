<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sub_issue_donor_parts', function (Blueprint $table) {
            // The donor vehicle is missing this part until it is put back.
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issue_donor_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restored_by');
            $table->dropColumn('restored_at');
        });
    }
};
