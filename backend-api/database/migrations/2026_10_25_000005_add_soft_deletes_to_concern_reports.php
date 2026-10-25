<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deleted concern reports stay in Concern History and can be restored.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('concern_reports', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('concern_reports', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
