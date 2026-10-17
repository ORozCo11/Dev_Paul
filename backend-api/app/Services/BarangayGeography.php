<?php

namespace App\Services;

use App\Models\Barangay;

class BarangayGeography
{
    public const SOURCE = 'namria_hdx_reference_2025';
    public const LEGACY_SOURCE = 'namria_psgc_reference_2023';

    /** Code-keyed reference geometry; never substitute a same-named barangay. */
    public function boundary(Barangay $barangay): ?array
    {
        if (!$barangay->psgc_code) {
            return null;
        }

        $cityCode = $barangay->city?->code;
        if (!$cityCode || !preg_match('/^\d{9}$/', $cityCode)) {
            return null;
        }

        // City shards keep full-detail polygons within normal PHP memory limits.
        $path = database_path('data/barangay-boundaries-detailed/'.$cityCode.'.json.gz');
        if (!is_file($path)) {
            return null;
        }

        $raw = gzdecode(file_get_contents($path));
        if ($raw === false) {
            throw new \RuntimeException('The bundled barangay boundary reference is unreadable.');
        }
        $geometries = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $geometry = $geometries[$barangay->psgc_code] ?? null;

        return in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true) ? $geometry : null;
    }

    public function resolvedBoundary(Barangay $barangay): ?array
    {
        if ($barangay->boundary !== null && !$this->hasAutomaticLegacyBoundary($barangay)) {
            return $barangay->boundary;
        }
        return $this->boundary($barangay);
    }

    public function hasAutomaticLegacyBoundary(Barangay $barangay): bool
    {
        return $barangay->boundary_source === self::LEGACY_SOURCE && $barangay->boundary_verified_by === null;
    }

    public function isUnreviewedReference(Barangay $barangay): bool
    {
        return in_array($barangay->boundary_source, [self::SOURCE, self::LEGACY_SOURCE], true)
            && $barangay->boundary_verified_by === null;
    }

    public function attachBoundary(Barangay $barangay): void
    {
        if ($barangay->boundary !== null && !$this->hasAutomaticLegacyBoundary($barangay)) {
            if ($this->isUnreviewedReference($barangay) && $barangay->boundary_status === 'verified') {
                $barangay->update([
                    'boundary_status' => 'needs_review', 'boundary_verified_at' => null,
                    'pending_boundary' => $barangay->pending_boundary ?? $barangay->boundary,
                    'pending_boundary_source' => $barangay->pending_boundary_source ?? $barangay->boundary_source,
                ]);
            }
            return;
        }
        $geometry = $this->boundary($barangay);
        if ($geometry) {
            $barangay->update([
                'boundary' => $geometry,
                'boundary_source' => self::SOURCE,
                'boundary_status' => 'needs_review',
                'boundary_verified_at' => null,
                'pending_boundary' => $barangay->pending_boundary ?? $geometry,
                'pending_boundary_source' => $barangay->pending_boundary_source ?? self::SOURCE,
            ]);
        } elseif ($this->hasAutomaticLegacyBoundary($barangay)) {
            // An unavailable detailed match must not keep serving a coarse triangle.
            $barangay->update(['boundary' => null, 'boundary_source' => null, 'boundary_status' => 'not_found', 'boundary_verified_at' => null]);
        }
    }
}
