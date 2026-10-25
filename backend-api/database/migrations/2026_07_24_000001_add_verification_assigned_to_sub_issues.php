<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->unsignedBigInteger('verification_assigned_to')->nullable()->after('verified_at');
            $table->foreign('verification_assigned_to')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sub_issues', function (Blueprint $table) {
            $table->dropForeign(['verification_assigned_to']);
            $table->dropColumn('verification_assigned_to');
        });
    }
};
