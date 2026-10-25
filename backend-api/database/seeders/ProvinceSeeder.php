<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Data snapshot from the PSA's PSGC reference API (psgc.gitlab.io),
     * trimmed to just {code, name} and stored in database/data/ so seeding
     * never depends on network access. Includes 3 pseudo-provinces
     * ("Metro Manila", "Cotabato City", "Isabela City") that group PSGC
     * cities with no province of their own (NCR cities + independent
     * cities) so every city still has exactly one parent to select under.
     */
    public function run(): void
    {
        $path = database_path('data/psgc-provinces.json');
        $provinces = json_decode(file_get_contents($path), true);

        foreach ($provinces as $province) {
            Province::firstOrCreate(
                ['name' => $province['name']],
                ['code' => $province['code']],
            );
        }
    }
}
