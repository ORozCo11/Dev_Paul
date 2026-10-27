<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Private files (storage/app/private) submitted at registration so the
            // reviewer can verify who is signing up. Never exposed as public URLs.
            $table->string('registration_id_path')->nullable();
            $table->string('registration_photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_id_path', 'registration_photo_path']);
        });
    }
};
