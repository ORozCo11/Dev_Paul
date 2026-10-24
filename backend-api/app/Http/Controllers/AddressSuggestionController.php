<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AddressSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['barangay_id' => ['required', 'integer', 'exists:barangays,id'], 'q' => ['required', 'string', 'min:3', 'max:150']]);
        $barangay = Barangay::with('city.province')->findOrFail($data['barangay_id']);
        try {
            $response = Http::timeout(4)->acceptJson()->get(config('services.photon.url'), [
                'q' => implode(', ', array_filter([trim($data['q']), $barangay->name, $barangay->city?->name, $barangay->city?->province?->name])),
                'countrycode' => 'PH', 'limit' => 5,
            ]);
            if (!$response->successful()) return response()->json([]);
            $suggestions = collect($response->json('features', []))->map(function ($feature) {
                $p = $feature['properties'] ?? [];
                if (strtoupper($p['countrycode'] ?? '') !== 'PH') return null;
                return implode(', ', array_unique(array_filter([
                    $p['name'] ?? null, trim(($p['housenumber'] ?? '').' '.($p['street'] ?? '')),
                    $p['district'] ?? null, $p['city'] ?? null,
                ])));
            })->filter()->unique()->take(5)->values();
            return response()->json($suggestions)->header('Cache-Control', 'no-store');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([]);
        }
    }
}
