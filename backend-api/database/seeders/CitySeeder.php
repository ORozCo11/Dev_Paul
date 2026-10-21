<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Data snapshot from the PSA's PSGC reference API, trimmed to
     * {code, name, provinceCode} — see ProvinceSeeder for why this is a
     * bundled file rather than a live fetch. Must run after ProvinceSeeder.
     */
    public function run(): void
    {
        $path = database_path('data/psgc-cities.json');
        $cities = json_decode(file_get_contents($path), true);

        $provinceIdsByCode = Province::pluck('id', 'code');

        foreach ($cities as $city) {
            $provinceId = $provinceIdsByCode[$city['provinceCode']] ?? null;

            if (!$provinceId) {
                continue;
            }

            City::updateOrCreate(
                ['province_id' => $provinceId, 'name' => $city['name']],
                ['code' => $city['code']],
            );
        }
    }
}
