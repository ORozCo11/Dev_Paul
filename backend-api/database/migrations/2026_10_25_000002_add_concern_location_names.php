<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('concern_reports', function (Blueprint $table) {
            $table->string('province_name')->nullable();
            $table->string('city_name')->nullable();
        });
    }
    public function down(): void {
        Schema::table('concern_reports', fn (Blueprint $table) => $table->dropColumn(['province_name', 'city_name']));
    }
};
