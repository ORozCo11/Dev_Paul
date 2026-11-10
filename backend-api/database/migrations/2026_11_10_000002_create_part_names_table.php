<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The list of part names offered when a cannibalized part is logged. Picking from one list keeps
 * the spelling the same, so the "this part is already missing" check cannot be fooled by a typo.
 * Users can add, rename and delete entries (like the other small catalogs).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('part_names')) {
            return;
        }
        Schema::create('part_names', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });
        DB::table('part_names')->insert(array_map(fn ($name) => ['name' => $name], [
            'Alternator', 'Battery', 'Brake system', 'Engine', 'Fire pump', 'Fuel pump', 'Headlight', 'Radiator',
            'Radio', 'Side mirror', 'Siren', 'Starter motor', 'Tires', 'Transmission', 'Water pump',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('part_names');
    }
};
