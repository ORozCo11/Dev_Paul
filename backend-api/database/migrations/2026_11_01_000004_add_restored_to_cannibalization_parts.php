<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cannibalization_parts', function (Blueprint $table) {
            // The source vehicle is missing this part until it is put back. No inventory is kept.
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cannibalization_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restored_by');
            $table->dropColumn('restored_at');
        });
    }
};
