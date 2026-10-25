<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Single-row settings table holding the Staff Registration Code — the
     * shared code the barangay office hands directly to real staff, that
     * the public Register form now requires before it'll create an
     * account. A full key-value settings table would be overkill for the
     * one value this needs to hold.
     */
    public function up(): void
    {
        Schema::create('registration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('staff_code');
            $table->timestamps();
        });

        DB::table('registration_settings')->insert([
            'staff_code' => Str::upper(Str::random(8)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_settings');
    }
};
