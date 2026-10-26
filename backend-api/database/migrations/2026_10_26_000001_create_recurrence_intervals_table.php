<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrence_intervals', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('name')->unique();
        });
        DB::table('recurrence_intervals')->insert(array_map(fn ($months) => ['name' => $months], [1, 3, 6, 12]));
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_intervals');
    }
};
