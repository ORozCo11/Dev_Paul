<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });
        DB::table('external_reasons')->insert(array_map(fn ($name) => ['name' => $name], [
            'Covered by dealer / manufacturer warranty',
            'Parts or service only available from an authorized shop',
            'Needs specialized shop equipment (e.g. alignment, A/C recovery, dyno)',
            'Requires licensed / certified service (e.g. emissions, LTO inspection)',
            'Mechanic assessed and recommended external repair',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('external_reasons');
    }
};
