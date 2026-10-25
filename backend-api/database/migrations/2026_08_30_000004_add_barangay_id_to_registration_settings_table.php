<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The staff registration code stops being one global secret and becomes
     * one per barangay — each barangay's Admin controls and can regenerate
     * only their own.
     */
    public function up(): void
    {
        Schema::table('registration_settings', function (Blueprint $table) {
            $table->foreignId('barangay_id')->nullable()->unique()->after('id')->constrained('barangays')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registration_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
        });
    }
};
