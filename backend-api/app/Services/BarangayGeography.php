<?php

namespace App\Services;

use App\Models\Barangay;

class BarangayGeography
{
    public const SOURCE = 'namria_hdx_reference_2025';
    public const LEGACY_SOURCE = 'namria_psgc_reference_2023';
    public const SOURCE_URL = 'https://data.humdata.org/dataset/cod-ab-phl';

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
        if ($this->usesStoredBoundary($barangay)) {
            return $barangay->boundary;
        }
        return $this->boundary($barangay);
    }

    public function hasAutomaticLegacyBoundary(Barangay $barangay): bool
    {
        return $barangay->boundary !== null && !$this->usesStoredBoundary($barangay);
    }

    public function usesStoredBoundary(Barangay $barangay): bool
    {
        // A documented administrator choice remains an explicit override. For
        // code-selected registration, every other stored polygon is a cache:
        // unsourced seed polygons and even an older SOURCE cache must never
        // override the current PSGC-keyed reference. Uncoded legacy tenants
        // cannot be selected on registration and keep their existing map.
        return $barangay->boundary !== null && (!$barangay->psgc_code
            || ($barangay->boundary_verified_by !== null && is_string($barangay->boundary_source) && trim($barangay->boundary_source) !== ''));
    }

    public function resolvedBoundarySource(Barangay $barangay): ?string
    {
        return $this->usesStoredBoundary($barangay) ? $barangay->boundary_source : self::SOURCE;
    }

    public function isUnreviewedReference(Barangay $barangay): bool
    {
        return in_array($barangay->boundary_source, [self::SOURCE, self::LEGACY_SOURCE, 'legacy'], true)
            && $barangay->boundary_verified_by === null;
    }

    /**
     * A boundary only counts as reviewed when a Super Admin confirmed an
     * uploaded replacement that names its source document. Imported
     * references, the pre-review `legacy` backfill and geocoder (OSM)
     * candidates are never reviewed, whatever their stored status says.
     */
    public static function isReviewedState(?string $status, ?string $source, $verifiedBy): bool
    {
        return $status === 'verified' && $verifiedBy !== null
            && is_string($source) && str_starts_with($source, self::UPLOADED_SOURCE_PREFIX);
    }

    public const UPLOADED_SOURCE_PREFIX = 'uploaded_reference: ';

    public function isReviewed(Barangay $barangay): bool
    {
        return $barangay->boundary !== null
            && self::isReviewedState($barangay->boundary_status, $barangay->boundary_source, $barangay->boundary_verified_by);
    }

    /** Ray-casting containment that honours holes and separate islands. */
    public static function containsPoint(array $geometry, float $lat, float $lng): bool
    {
        $polygons = ($geometry['type'] ?? null) === 'MultiPolygon' ? ($geometry['coordinates'] ?? []) : [$geometry['coordinates'] ?? []];
        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || !$polygon) continue;
            if (!self::ringContains($polygon[0], $lng, $lat)) continue;
            $inHole = false;
            foreach (array_slice($polygon, 1) as $hole) {
                if (self::ringContains($hole, $lng, $lat)) { $inHole = true; break; }
            }
            if (!$inHole) return true;
        }
        return false;
    }

    public static function ringContains(array $ring, float $x, float $y): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    /**
     * Bounding box [minLng, minLat, maxLng, maxLat] of every bundled reference
     * polygon in the barangay's city/municipality, or null when that shard is
     * unavailable. Used as a coarse sanity check on uploaded replacements.
     */
    public function cityExtent(Barangay $barangay): ?array
    {
        $cityCode = $barangay->city?->code;
        if (!$cityCode || !preg_match('/^\d{9}$/', $cityCode)) return null;
        $path = database_path('data/barangay-boundaries-detailed/'.$cityCode.'.json.gz');
        if (!is_file($path) || ($raw = gzdecode(file_get_contents($path))) === false) return null;
        $extent = null;
        foreach (json_decode($raw, true, flags: JSON_THROW_ON_ERROR) as $geometry) {
            $extent = self::extendExtent($extent, $geometry);
        }
        return $extent;
    }

    public static function extendExtent(?array $extent, array $geometry): ?array
    {
        $polygons = ($geometry['type'] ?? null) === 'MultiPolygon' ? ($geometry['coordinates'] ?? []) : [$geometry['coordinates'] ?? []];
        foreach ($polygons as $polygon) {
            foreach ($polygon[0] ?? [] as [$lng, $lat]) {
                $extent = $extent === null ? [$lng, $lat, $lng, $lat]
                    : [min($extent[0], $lng), min($extent[1], $lat), max($extent[2], $lng), max($extent[3], $lat)];
            }
        }
        return $extent;
    }

    public function attachBoundary(Barangay $barangay): void
    {
        if ($this->usesStoredBoundary($barangay)) {
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
        $values = [
            'boundary' => $geometry,
            'boundary_source' => $geometry ? self::SOURCE : null,
            'boundary_status' => $geometry ? 'needs_review' : 'not_found',
            'boundary_verified_at' => null,
            'boundary_verified_by' => null,
        ];
        // Keep a replacement uploaded for review, but replace stale automatic
        // candidates too; otherwise Confirm could resurrect the wrong outline.
        if (!is_string($barangay->pending_boundary_source) || !str_starts_with($barangay->pending_boundary_source, self::UPLOADED_SOURCE_PREFIX)) {
            $values['pending_boundary'] = $geometry;
            $values['pending_boundary_source'] = $geometry ? self::SOURCE : null;
        }
        $barangay->fill($values);
        if ($barangay->isDirty()) $barangay->save();
    }
}
