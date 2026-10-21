<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->string('psgc_code', 10)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropUnique(['psgc_code']);
            $table->dropColumn('psgc_code');
        });
    }
};
