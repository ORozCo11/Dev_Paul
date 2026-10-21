<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_archive_logs', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('ticket_sub_issues', fn (Blueprint $table) => $table->json('attachment_files')->nullable());
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', fn (Blueprint $table) => $table->dropColumn('attachment_files'));
        Schema::table('ticket_archive_logs', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
