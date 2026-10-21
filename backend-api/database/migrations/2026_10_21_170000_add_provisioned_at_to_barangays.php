<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('barangays', fn (Blueprint $table) => $table->timestamp('provisioned_at')->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->dropIndex(['provisioned_at']);
            $table->dropColumn('provisioned_at');
        });
    }
};
