<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Public list for the registration form's cascading City/Municipality
     * dropdown — always scoped to a province.
     */
    public function index(Request $request)
    {
        $request->validate([
            'province_id' => ['required', 'exists:provinces,id'],
        ]);

        return City::where('province_id', $request->province_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Single city lookup, boundary geometry included — used by the admin
     * Vehicle Location map's barangay/city boundary selector. Public, same
     * as index(): boundary polygons aren't sensitive data.
     */
    public function show(City $city)
    {
        return $city->setVisible(['id', 'name', 'boundary']);
    }
}
