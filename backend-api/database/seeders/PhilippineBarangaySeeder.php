<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhilippineBarangaySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = json_decode(file_get_contents(database_path('data/psgc-barangays.json')), true, flags: JSON_THROW_ON_ERROR);
        $cities = City::whereNotNull('code')->get()->keyBy('code');
        $normalize = static fn ($name) => strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
        $count = 0;

        foreach ($catalog as $cityCode => $entries) {
            $city = $cities->get($cityCode);
            if (!$city) {
                throw new \RuntimeException("Missing parent city {$cityCode}; run ProvinceSeeder and CitySeeder first.");
            }
            DB::transaction(function () use ($city, $entries, $normalize, &$count) {
                $existing = Barangay::where('city_id', $city->id)->get();
                $byCode = $existing->whereNotNull('psgc_code')->keyBy('psgc_code');
                $nameCounts = array_count_values(array_map(fn ($entry) => strtolower($entry['name']), $entries));
                foreach ($entries as $entry) {
                    if ($byCode->has($entry['psgc_code'])) {
                        $count++;
                        continue;
                    }
                    // A few cities have distinct official barangays sharing one name.
                    // Disambiguate these by code, rather than merging their identities.
                    $ambiguousName = $nameCounts[strtolower($entry['name'])] > 1;
                    $name = $ambiguousName ? $entry['name'].' [PSGC '.$entry['psgc_code'].']' : $entry['name'];
                    $key = $normalize($name);
                    $aliases = $city->code === '072230000'
                        ? ['centropob' => 'centropoblacion', 'paknaan' => 'paknaan'] : [];
                    $key = $aliases[$key] ?? $key;
                    $matches = $existing->filter(fn ($row) => !$row->psgc_code && $normalize($row->name) === $key);
                    if ($matches->count() === 1) {
                        // Preserve tenant IDs, account associations, existing geometry and codes.
                        $matches->first()->update(['psgc_code' => $entry['psgc_code']]);
                    } else {
                        Barangay::create(['city_id' => $city->id, 'name' => $name, 'psgc_code' => $entry['psgc_code']]);
                    }
                    $count++;
                }
            });
        }
        $this->command?->info("PSGC barangay references ready: {$count}.");
    }
}
