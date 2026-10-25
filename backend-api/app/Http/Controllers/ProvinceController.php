<?php

namespace App\Http\Controllers;

use App\Models\Province;

class ProvinceController extends Controller
{
    /**
     * Public list for the registration form's province dropdown.
     */
    public function index()
    {
        return Province::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Map selector hierarchy for registered barangays (plus the original
     * Paknaan service area). Return every eligible city in a province.
     * The legacy first-city fields remain for older clients.
     */
    public function withBarangays()
    {
        return Province::whereHas('cities', fn ($query) => $query->whereHas('barangays', fn ($barangays) => $barangays->whereHas('users')->orWhere('name', 'Paknaan')))
            ->with(['cities' => fn ($query) => $query->whereHas('barangays', fn ($barangays) => $barangays->whereHas('users')->orWhere('name', 'Paknaan'))->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Province $province) {
                $city = $province->cities->first();

                return [
                    'id' => $province->id,
                    'name' => $province->name,
                    'city_with_barangays_id' => $city?->id,
                    'city_with_barangays_name' => $city?->name,
                    'cities' => $province->cities->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])->values(),
                ];
            });
    }
}
