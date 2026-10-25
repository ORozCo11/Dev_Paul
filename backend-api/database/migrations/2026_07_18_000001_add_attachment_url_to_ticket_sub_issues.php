<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a mechanic attach one photo or document (receipt, before/after
     * shot, etc.) when logging a repair — same single-attachment convention
     * already used for Vehicle and Issue Report photos elsewhere.
     */
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->string('attachment_url')->nullable()->after('parts_used');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropColumn('attachment_url');
        });
    }
};
