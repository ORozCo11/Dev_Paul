<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\VehicleCategory;
use App\Models\VehicleHub;
use Illuminate\Database\Seeder;

class FleetReferenceSeeder extends Seeder
{
    /**
     * Seed standard vehicle classifications used by dropdowns.
     */
    public function run(): void
    {
        $categories = [
            'Ambulance' => 'Land',
            'Fire Truck' => 'Land',
            'Truck' => 'Land',
            'Van' => 'Land',
            'Patrol Vehicle' => 'Land',
            'Service Vehicle' => 'Land',
            'Rescue Boat' => 'Water',
        ];

        foreach ($categories as $category => $domain) {
            VehicleCategory::firstOrCreate(
                ['category_name' => $category],
                ['domain' => $domain, 'description' => "{$category} fleet classification"],
            );
        }

        $this->seedDefaultHubs();
    }

    /**
     * Seed the fixed Pahina Central vehicle hubs the fleet map/location dropdowns rely on.
     * These used to live only in frontend localStorage — moved server-side so every
     * user shares the same hub list.
     *
     * These three are real Pahina Central locations (Twinbee Hub, the Pahina
     * Brgy Hall, the Pahina Gymnasium) — not generic examples every
     * barangay could plausibly reuse — so they're stamped with Pahina Central's own
     * barangay_id rather than left null. A hub row with a null barangay_id
     * is invisible to every tenant (BelongsToBarangay's global scope filters
     * on `WHERE barangay_id = <user's>`, which never matches NULL), which
     * would otherwise leave every barangay — Pahina Central included — with zero
     * hubs to pick from and unable to add any vehicle at all (validateVehicle()
     * requires current_location to be one of VehicleHub::pluck('name')). Any
     * other barangay still starts with no hubs of its own; its Admin adds
     * real ones for its own locations via POST /hubs, same as it does for
     * everything else in this per-barangay tenant model.
     */
    private function seedDefaultHubs(): void
    {
        $homeBarangayId = Barangay::where('name', 'Pahina Central (Pob.)')->whereHas('city', fn ($q) => $q->where('name', 'Cebu City'))->value('id');

        $hubs = [
            [
                'hub_key' => 'twinbee-hub',
                'name' => 'Twinbee Hub',
                'label' => 'TB',
                'lat' => 10.345909307605107,
                'lng' => 123.95748834311513,
                'match_names' => ['twinbee hub', 'twinbee', 'palanan central hub', 'palanan central', 'palanan'],
            ],
            [
                'hub_key' => 'paknaan-brgy-hall',
                'name' => 'Pahina Brgy Hall',
                'label' => 'BH',
                'lat' => 10.34631777531932,
                'lng' => 123.96022810316107,
                'match_names' => ['paknaan brgy hall', 'paknaan barangay hall', 'barangay hall hub', 'brgy hall hub', 'brgy hall', 'barangay hall'],
            ],
            [
                'hub_key' => 'paknaan-gymnasium',
                'name' => 'Pahina Gymnasium',
                'label' => 'GY',
                'lat' => 10.346474742138703,
                'lng' => 123.95854066966143,
                'match_names' => ['paknaan gymnasium', 'paknaan gymnasium hub', 'gymnasium hub', 'gymnasium', 'san isidro depot', 'san isidro', 'depot'],
            ],
        ];

        foreach ($hubs as $hub) {
            VehicleHub::firstOrCreate(
                ['hub_key' => $hub['hub_key']],
                $hub + ['is_default' => true, 'barangay_id' => $homeBarangayId],
            );
        }
    }
}
