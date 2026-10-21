<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Concerns\GuardsLastAdmin;
use App\Http\Controllers\Concerns\GuardsOpenWorkOnDeactivation;
use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\ConcernReport;
use App\Models\RegistrationSetting;
use App\Models\User;
use App\Services\BarangayGeography;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Platform-level account administration — the one tier above every
 * barangay's own Admin. Deliberately scoped to ACCOUNTS only (users,
 * barangays, registration codes, impersonation, the audit log): a Super
 * Admin can never see a barangay's vehicles, tickets, or other fleet data.
 * That boundary is what keeps "separate everything per barangay" true even
 * with this role in the system — see BelongsToBarangay's docblock for why
 * User itself is never scoped, which is exactly what lets a barangay_id-less
 * Super Admin see across every barangay here without any special-casing.
 */
class SuperAdminController extends Controller
{
    use GuardsLastAdmin;
    use GuardsOpenWorkOnDeactivation;
    use AuthorizesAbilities;

    private const ROLES = ['Admin', 'Custodian', 'Maintenance Personnel'];

    /**
     * `activity_logs.module` values that represent account/administrative-
     * level activity — the only entries activityLog() may return, per this
     * class's docblock. Every other module string in that table is fleet-
     * operational detail logged by FleetController/TicketController for a
     * barangay's own Admin audit trail (e.g. 'Vehicle Management', 'Vehicle
     * Maintenance Records', 'Vehicle Maintenance Schedule', 'Vehicle
     * Condition Monitoring', 'Vehicle Issue Reports', 'Vehicle Documents',
     * 'Vehicle Categories', 'Reports', 'Maintenance Tickets') and must never
     * surface here. Using an allowlist (rather than excluding fleet modules
     * by name) means a newly added fleet module is excluded by default.
     */
    private const ACCOUNT_MODULES = ['Super Admin', 'Dev Tools', 'Profile'];

    private function log(Request $request, string $action, string $details): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'role'    => $request->user()->role,
            'action'  => $action,
            'module'  => 'Super Admin',
            'details' => $details,
        ]);
    }

    /**
     * Every barangay, with its staff count and whether it currently has an
     * active Admin — an "orphaned" barangay (none) is the case this whole
     * role exists to let someone recover from.
     */
    public function barangays(Request $request)
    {
        $this->requireAbility($request, 'barangay.view_all', 'Only a Super Admin can perform this action.');

        return Barangay::with(['city.province', 'registrationSetting'])
            // Reference-only PSGC rows are registration choices, not platform tenants.
            // Keep manually added barangays visible before their first staff joins.
            ->where(function ($query) {
                $query->whereNull('psgc_code')->orWhereHas('users')->orWhereHas('registrationSetting');
            })
            ->select(['id', 'name', 'psgc_code', 'city_id', 'boundary_status', 'boundary_source', 'boundary_verified_by', 'pending_boundary', 'pending_boundary_source'])
            ->selectRaw('CASE WHEN boundary IS NULL THEN 0 ELSE 1 END AS boundary_present')
            ->withCount([
                'users',
                'users as active_admin_count' => fn ($query) => $query->havingRole('Admin')->where('is_active', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($b) {
                // One query, two derived facts — has_active_admin was
                // previously its own separate ->exists() query; counting
                // instead also gives Phase A4's "only one Admin left" signal
                // for free.
                $activeAdminCount = $b->active_admin_count;

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'psgc_code' => $b->psgc_code,
                    'city_name' => $b->city?->name,
                    'province_name' => $b->city?->province?->name,
                    'staff_count' => $b->users_count,
                    'has_active_admin' => $activeAdminCount > 0,
                    // VMS-IMPROVEMENT-PLAN.md Phase A4 — one departure away
                    // from becoming a `has_active_admin: false` orphaned
                    // barangay (see GuardsLastAdmin, which blocks that
                    // departure from happening through the ordinary
                    // deactivate/role-change endpoints, but not e.g. the
                    // Admin simply leaving with no successor promoted).
                    'sole_active_admin' => $activeAdminCount === 1,
                    // Both one-line, already-loaded checks — no extra queries
                    // per row. A barangay can exist with neither yet: freshly
                    // added via storeBarangay() below, nobody's registered
                    // there, and no boundary polygon has been matched/drawn
                    // for it.
                    'has_registration_code' => $b->registrationSetting !== null,
                    'has_boundary' => (bool) $b->boundary_present,
                    // Final stabilization — a real status instead of a bare
                    // boolean. 'needs_review' is derived from a pending match
                    // existing, not its own stored value, so there's exactly
                    // one place a boundary is "live" and one it's "proposed".
                    // A live outline only reads as 'verified' (reviewed) when it
                    // came from a confirmed upload naming its source document.
                    'boundary_status' => $b->pending_boundary !== null ? 'needs_review'
                        : ($b->boundary_present ? (BarangayGeography::isReviewedState($b->boundary_status, $b->boundary_source, $b->boundary_verified_by) ? 'verified' : 'needs_review') : $b->boundary_status),
                    'pending_boundary' => $b->pending_boundary,
                    'pending_boundary_source' => $b->pending_boundary_source,
                ];
            });
    }

    /**
     * Provisions an existing PSA PSGC reference barangay.  This intentionally
     * accepts no free-text name and never calls a geocoder: a boundary is
     * selected solely by its official PSGC identity, or remains unavailable.
     */
    public function storeBarangay(Request $request)
    {
        $this->requireAbility($request, 'barangay.create', 'Only a Super Admin can perform this action.');

        $data = $request->validate([
            'city_id' => ['required', 'exists:cities,id'],
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
        ]);
        $barangay = Barangay::with('city.province')->findOrFail($data['barangay_id']);
        abort_unless($barangay->city_id === (int) $data['city_id'] && $barangay->psgc_code,
            422, 'Select an official PSGC barangay belonging to the selected city or municipality.');
        $barangay->load('city.province');
        app(BarangayGeography::class)->attachBoundary($barangay);
        $barangay->refresh();

        $this->log($request, 'Add', "Added barangay {$barangay->name} ({$barangay->city?->name}).");

        return response()->json([
            'id' => $barangay->id,
            'name' => $barangay->name,
            'city_name' => $barangay->city?->name,
            'province_name' => $barangay->city?->province?->name,
            'staff_count' => 0,
            'has_active_admin' => false,
            'has_registration_code' => false,
            'psgc_code' => $barangay->psgc_code,
            'has_boundary' => $barangay->boundary !== null,
            'boundary_status' => $barangay->boundary !== null ? 'needs_review' : 'not_found',
            'pending_boundary' => $barangay->pending_boundary,
            'pending_boundary_source' => $barangay->pending_boundary_source,
        ], 201);
    }

    /**
     * Re-checks the code-matched PSA/NAMRIA reference bundle. No geocoder,
     * name matching, generated geometry, or fallback barangay is permitted.
     */
    public function refreshBarangayBoundary(Request $request, Barangay $barangay)
    {
        $this->requireAbility($request, 'barangay.refresh_boundary', 'Only a Super Admin can perform this action.');

        $barangay->load('city');
        app(BarangayGeography::class)->attachBoundary($barangay);
        $barangay->refresh();
        $found = $barangay->boundary !== null;
        if (!$found) $barangay->update(['boundary_status' => 'not_found']);

        return response()->json([
            'has_boundary' => $found,
            'boundary_status' => $found ? 'needs_review' : 'not_found',
            'pending_boundary' => $barangay->pending_boundary,
            'pending_boundary_source' => $barangay->pending_boundary_source,
        ]);
    }

    /**
     * Final stabilization (§33.2/§33.6) — the Super Admin's explicit
     * acceptance of a `pending_boundary` candidate. This is the ONLY place
     * `boundary` is ever written to from a geocoder result; nothing upstream
     * auto-confirms. Logged with before/after so a boundary's provenance is
     * always answerable later.
     */
    public function uploadBoundaryCandidate(Request $request, Barangay $barangay)
    {
        $this->requireAbility($request, 'barangay.refresh_boundary', 'Only a Super Admin can perform this action.');
        $data = $request->validate([
            'geojson' => ['required', 'array'],
            'source' => ['required', 'string', 'max:200'],
            'psgc_code' => ['nullable', 'string'],
        ]);
        abort_unless(($data['psgc_code'] ?? null) === $barangay->psgc_code, 422, 'The selected barangay code does not match.');
        $feature = $data['geojson'];
        if (($feature['type'] ?? null) === 'FeatureCollection') {
            abort_unless(is_array($feature['features'] ?? null) && array_is_list($feature['features']) && count($feature['features']) === 1 && is_array($feature['features'][0]), 422, 'Choose a file containing exactly one barangay feature.');
            $feature = $feature['features'][0];
        }
        if (($feature['type'] ?? null) === 'Feature') {
            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            foreach (['psgc_code', 'psgc_10d', 'psgc', 'ADM4_PCODE', 'adm4_pcode'] as $key) {
                if (!isset($properties[$key])) continue;
                // COD-AB style codes carry a "PH" prefix; compare digits only.
                $code = preg_replace('/\D/', '', (string) $properties[$key]);
                abort_if($code !== (string) $barangay->psgc_code, 422, 'The GeoJSON feature belongs to a different barangay.');
            }
            $geometry = $feature['geometry'] ?? [];
        } else {
            $geometry = $feature;
        }
        abort_unless(is_array($geometry) && in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true) && self::isUsableGeometry($geometry), 422, 'Provide a valid, closed Polygon or MultiPolygon, including valid holes.');
        abort_unless(self::holesInsideShells($geometry), 422, 'Every hole must lie inside its outer ring.');
        // Coarse location sanity check: the outline must sit within ~5km of
        // the municipality's bundled reference extent. Skipped when no
        // reference shard exists for the city.
        $barangay->loadMissing('city');
        $extent = app(BarangayGeography::class)->cityExtent($barangay);
        if ($extent !== null) {
            $candidate = BarangayGeography::extendExtent(null, $geometry);
            $margin = 0.05;
            abort_if($candidate[2] < $extent[0] - $margin || $candidate[0] > $extent[2] + $margin || $candidate[3] < $extent[1] - $margin || $candidate[1] > $extent[3] + $margin,
                422, "The outline does not fall within {$barangay->city?->name}. Check that the file uses WGS84 longitude, latitude coordinates.");
        }
        $source = BarangayGeography::UPLOADED_SOURCE_PREFIX.trim($data['source']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($barangay, $geometry, $source, $request) {
            $barangay->update(['pending_boundary' => $geometry, 'pending_boundary_source' => $source]);
            $this->log($request, 'Edit', "Uploaded a replacement boundary for {$barangay->name} (PSGC {$barangay->psgc_code}); source: {$source}; awaiting confirmation.");
        });
        return response()->json(['pending_boundary' => $geometry, 'pending_boundary_source' => $source, 'boundary_status' => 'needs_review']);
    }

    public function confirmBarangayBoundary(Request $request, Barangay $barangay)
    {
        $this->requireAbility($request, 'barangay.refresh_boundary', 'Only a Super Admin can perform this action.');

        abort_if($barangay->pending_boundary === null, 422, 'There is no candidate boundary waiting for review on this barangay.');

        $barangay->load('city');
        $source = $barangay->pending_boundary_source;
        // Only an uploaded file that names its source document can become a
        // reviewed (containment-enforcing) boundary. A geocoder or imported
        // reference candidate is applied as the live outline but stays
        // unverified: OSM/reference geometry is not legal boundary evidence.
        // The applying admin is still recorded so automatic refreshes keep
        // preserving the outline they chose.
        $reviewed = is_string($source) && str_starts_with($source, BarangayGeography::UPLOADED_SOURCE_PREFIX);

        $barangay->update([
            'boundary' => $barangay->pending_boundary,
            'boundary_status' => $reviewed ? 'verified' : 'needs_review',
            'boundary_source' => $source,
            'boundary_verified_at' => now(),
            'boundary_verified_by' => $request->user()->id,
            'pending_boundary' => null,
            'pending_boundary_source' => null,
        ]);

        $this->log($request, 'Edit', $reviewed
            ? "Confirmed the reviewed boundary for barangay {$barangay->name} ({$barangay->city?->name}) — source: {$source}."
            : "Applied an unverified reference outline for barangay {$barangay->name} ({$barangay->city?->name}) — source: {$source}.");

        return response()->json(['has_boundary' => true, 'boundary_status' => $reviewed ? 'verified' : 'needs_review']);
    }

    /**
     * Rejects a `pending_boundary` candidate without touching `boundary` —
     * e.g. the Super Admin looked at the map preview and it's visibly wrong.
     * Leaves the barangay exactly where it was before the lookup that
     * produced it (still 'not_found' if nothing was confirmed before).
     */
    public function discardPendingBoundary(Request $request, Barangay $barangay)
    {
        $this->requireAbility($request, 'barangay.refresh_boundary', 'Only a Super Admin can perform this action.');

        abort_if($barangay->pending_boundary === null, 422, 'There is no candidate boundary waiting for review on this barangay.');

        $barangay->load('city');
        $barangay->update([
            'pending_boundary' => null,
            'pending_boundary_source' => null,
            'boundary_status' => $barangay->boundary !== null ? (app(BarangayGeography::class)->isReviewed($barangay) ? 'verified' : 'needs_review') : 'not_found',
        ]);

        $this->log($request, 'Edit', "Discarded a candidate boundary for barangay {$barangay->name} ({$barangay->city?->name}) — did not match.");

        return response()->json(['message' => 'Candidate boundary discarded.']);
    }

    /**
     * Asks OpenStreetMap's public Nominatim geocoder for a boundary polygon
     * matching "<barangay>, <city>, Philippines" — free, no API key, but
     * rate-limited to ~1 req/sec and requires an identifying User-Agent per
     * its usage policy (https://operations.osmfoundation.org/policies/nominatim/).
     * Fine for this: one lookup per barangay actually added, never a bulk
     * operation. Coverage isn't guaranteed for every barangay in the
     * country — returns null (silently — this must never block a barangay
     * from being created) whenever nothing usable comes back.
     *
     * Nominatim's free-text ranking isn't trustworthy enough to take its
     * first "administrative boundary" hit as-is: PH barangay names repeat
     * constantly nationwide (there are dozens of "San Isidro"s, "Poblacion"s,
     * "San Roque"s...), and `class=boundary`/`type=administrative` matches
     * ANY admin tier — barangay, city/municipality, province, or region —
     * not specifically the barangay. Left unchecked, that silently attaches
     * a same-named barangay's polygon from a different province, or the
     * whole containing city's/province's polygon, to this barangay. So every
     * candidate is cross-checked against its own `address` breakdown before
     * being trusted: it must resolve to the requested city, and it must
     * actually name the barangay at barangay-level granularity — not just
     * be a match on the city/province. No match surviving that means no
     * boundary, same as if the lookup had found nothing at all.
     */
    private function lookupBoundary(string $barangayName, ?string $cityName): ?array
    {
        if (!$cityName) {
            return null;
        }

        // Two phrasings, tried in order until one produces a validated match.
        // OSM contributors are inconsistent about whether a barangay relation
        // is named "San Isidro" or "Barangay San Isidro" — plain free-text
        // search sometimes ranks the bare form above the "Barangay "-prefixed
        // one for the same place (or vice versa) depending on what else on
        // OSM shares that name, so both are worth a try before giving up.
        $queries = [
            "{$barangayName}, {$cityName}, Philippines",
            "Barangay {$barangayName}, {$cityName}, Philippines",
        ];

        foreach ($queries as $i => $query) {
            // Nominatim's usage policy caps this at ~1 req/sec; only the
            // fallback attempt needs a beat before it, since it only runs
            // when the first request has already returned.
            if ($i > 0) {
                usleep(1_000_000);
            }

            $geojson = $this->lookupBoundaryForQuery($query, $barangayName, $cityName);
            if ($geojson) {
                return $geojson;
            }
        }

        return null;
    }

    private function lookupBoundaryForQuery(string $query, string $barangayName, string $cityName): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'BarangayVMS/1.0 (https://github.com/ORozCo11/Dev_Paul)'])
                ->timeout(6)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'polygon_geojson' => 1,
                    'addressdetails' => 1,
                    'countrycodes' => 'ph',
                    'limit' => 5,
                    'q' => $query,
                ]);

            if (!$response->successful()) {
                return null;
            }

            $normalizedCity = self::normalizeForMatch($cityName);
            $normalizedBarangay = self::normalizeForMatch($barangayName);

            foreach ($response->json() ?? [] as $result) {
                // Only trust an actual administrative boundary relation — anything
                // else (a barangay hall, a health center, a POI) can still carry a
                // Polygon/MultiPolygon geojson but it's the shape of a building, not
                // the barangay's territory, and would draw the wrong line on the map.
                if (($result['class'] ?? null) !== 'boundary' || ($result['type'] ?? null) !== 'administrative') {
                    continue;
                }

                $geojson = $result['geojson'] ?? null;
                if (!in_array($geojson['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
                    continue;
                }
                if (!self::isUsableGeometry($geojson)) {
                    continue;
                }

                // Reject anything outside the requested city — a same-named
                // barangay elsewhere in the country must not be accepted just
                // because it ranked in the top 5 results.
                $address = $result['address'] ?? [];
                $resultCity = $address['city'] ?? $address['municipality'] ?? $address['town'] ?? null;
                if (!$resultCity || self::normalizeForMatch($resultCity) !== $normalizedCity) {
                    continue;
                }

                // Reject a match that's actually the whole city/municipality
                // (or province) rather than the barangay itself — too coarse
                // to draw as one barangay's territory. A genuine barangay-
                // level match names the barangay somewhere in its own
                // address breakdown (OSM tags it as village/suburb/quarter/
                // neighbourhood/city_district depending on how it was mapped).
                $barangayLevelNames = array_filter([
                    $address['village'] ?? null,
                    $address['suburb'] ?? null,
                    $address['quarter'] ?? null,
                    $address['neighbourhood'] ?? null,
                    $address['city_district'] ?? null,
                ]);
                $matchesBarangay = false;
                foreach ($barangayLevelNames as $candidate) {
                    if (self::normalizeForMatch($candidate) === $normalizedBarangay) {
                        $matchesBarangay = true;
                        break;
                    }
                }
                if (!$matchesBarangay) {
                    continue;
                }

                return $geojson;
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning("Boundary lookup failed for {$barangayName}, {$cityName} (query \"{$query}\"): {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Same normalization BarangayBoundarySeeder uses to match the Mandaue
     * boundary file's names against seeded rows — strip everything but
     * letters/digits and lowercase, so "Centro (Poblacion)" vs "Centro",
     * "Pakna-an" vs "Paknaan", casing, and stray whitespace never cause a
     * real match to be rejected.
     */
    private static function normalizeForMatch(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
    }

    /**
     * Final stabilization — a geocoder response succeeding at the HTTP level
     * (and even passing the class/type/address-match checks above) doesn't
     * guarantee the geometry itself is usable: a degenerate ring (too few
     * points, not closed) or non-numeric coordinates would still draw
     * garbage on the map. Checks every shell and hole for closure, nonzero
     * area and finite geographic coordinates. This is basic validation,
     * not a cadastral or full topological validation of the uploaded data.
     */
    private static function isUsableGeometry(array $geojson): bool
    {
        if (!is_array($geojson['coordinates'] ?? null) || !array_is_list($geojson['coordinates'])) return false;
        $polygons = $geojson['type'] === 'MultiPolygon' ? ($geojson['coordinates'] ?? []) : [$geojson['coordinates'] ?? null];

        $points = 0;
        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || !array_is_list($polygon) || !$polygon) return false;
            foreach ($polygon as $ring) {
                if (!self::isUsableRing($ring)) return false;
                $points += count($ring);
                if ($points > 50000) return false;
            }
        }

        return !empty($polygons);
    }

    /** Each hole's vertices must lie inside, or on, its polygon's outer ring. */
    private static function holesInsideShells(array $geojson): bool
    {
        $polygons = $geojson['type'] === 'MultiPolygon' ? $geojson['coordinates'] : [$geojson['coordinates']];
        foreach ($polygons as $polygon) {
            foreach (array_slice($polygon, 1) as $hole) {
                foreach ($hole as $point) {
                    $x = (float) $point[0];
                    $y = (float) $point[1];
                    if (!BarangayGeography::ringContains($polygon[0], $x, $y) && !self::onRing($polygon[0], $x, $y)) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private static function onRing(array $ring, float $x, float $y): bool
    {
        $epsilon = 1e-9;
        for ($i = 1; $i < count($ring); $i++) {
            [$ax, $ay] = $ring[$i - 1];
            [$bx, $by] = $ring[$i];
            $cross = ($bx - $ax) * ($y - $ay) - ($by - $ay) * ($x - $ax);
            if (abs($cross) <= $epsilon && $x >= min($ax, $bx) - $epsilon && $x <= max($ax, $bx) + $epsilon
                && $y >= min($ay, $by) - $epsilon && $y <= max($ay, $by) + $epsilon) {
                return true;
            }
        }
        return false;
    }

    private static function isUsableRing($ring): bool
    {
        if (!is_array($ring) || !array_is_list($ring) || count($ring) < 4) {
            return false;
        }

        foreach ($ring as $point) {
            if (!is_array($point) || !array_is_list($point) || count($point) < 2 || !(is_int($point[0]) || is_float($point[0])) || !(is_int($point[1]) || is_float($point[1])) || !is_finite((float) $point[0]) || !is_finite((float) $point[1]) || abs($point[0]) > 180 || abs($point[1]) > 90) {
                return false;
            }
        }

        $first = $ring[0];
        $last = $ring[count($ring) - 1];
        $area = 0;
        for ($i = 1; $i < count($ring); $i++) $area += $ring[$i - 1][0] * $ring[$i][1] - $ring[$i][0] * $ring[$i - 1][1];
        return $area != 0 && (float) $first[0] === (float) $last[0] && (float) $first[1] === (float) $last[1];
    }

    /**
     * Every user, across every barangay — the one screen in the whole
     * system where that's true. Search matches name or email.
     */
    public function users(Request $request)
    {
        $this->requireAbility($request, 'user.view_all_platform', 'Only a Super Admin can perform this action.');

        $query = User::with('barangay.city.province');

        if ($request->filled('q')) {
            $search = $request->string('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return $query->orderBy('name')->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'roles' => $u->roles,
            'is_active' => $u->is_active,
            'approved_at' => $u->approved_at,
            'barangay_id' => $u->barangay_id,
            'barangay_name' => $u->barangay?->name,
            'city_name' => $u->barangay?->city?->name,
            'province_name' => $u->barangay?->city?->province?->name,
        ]);
    }

    public function activateUser(Request $request, User $user)
    {
        $this->requireAbility($request, 'user.activate_platform', 'Only a Super Admin can perform this action.');

        $user->update(['is_active' => true, 'approved_at' => $user->approved_at ?? now()]);
        $this->log($request, 'Activate', "Activated {$user->name} ({$user->email}).");

        return response()->json(['message' => 'User activated.']);
    }

    public function deactivateUser(Request $request, User $user)
    {
        $this->requireAbility($request, 'user.deactivate_platform', 'Only a Super Admin can perform this action.');
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own account.');
        $this->abortIfLastActiveAdmin($user, 'deactivating them');
        $this->abortIfHasOpenWork($user, 'deactivating them');

        $user->update(['is_active' => false]);
        $user->tokens()->delete();
        $this->log($request, 'Deactivate', "Deactivated {$user->name} ({$user->email}).");

        return response()->json(['message' => 'User deactivated.']);
    }

    /**
     * Rejects a registration that was never approved — deletes the account
     * outright rather than just deactivating it, since a rejected signup
     * (e.g. someone who wasn't actually the barangay's real first Admin)
     * has no legitimate record worth keeping around in an inactive state.
     * Deliberately refuses to touch anyone who was EVER approved (even if
     * later deactivated) — that path only goes through deactivateUser().
     */
    public function rejectUser(Request $request, User $user)
    {
        $this->requireAbility($request, 'user.reject', 'Only a Super Admin can perform this action.');
        abort_if($user->approved_at !== null, 422, 'This account was already approved at some point — deactivate it instead of rejecting.');

        $name = $user->name;
        $email = $user->email;
        $user->delete();
        $this->log($request, 'Delete', "Rejected pending registration for {$name} ({$email}).");

        return response()->json(['message' => 'Registration rejected.']);
    }

    /**
     * The specific queue this role exists to guard: every account still
     * waiting on approval, newest first. Not just first-for-barangay Admins
     * (an Admin can, in principle, sit unapproved if a barangay's own Admin
     * never got around to reviewing them) — but Super Admin approving a
     * pending Admin is the case that closes the registration hole.
     */
    public function pendingApprovals(Request $request)
    {
        $this->requireAbility($request, 'user.view_pending', 'Only a Super Admin can perform this action.');

        return User::with('barangay.city.province')
            ->whereNull('approved_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'phone' => $u->phone,
                'created_at' => $u->created_at,
                'barangay_id' => $u->barangay_id,
                'barangay_name' => $u->barangay?->name ?? $u->barangay_name,
                'city_name' => $u->barangay?->city?->name,
                'province_name' => $u->barangay?->city?->province?->name,
            ]);
    }

    /**
     * Change a user's role — also how an orphaned barangay gets recovered:
     * pick an existing user there and set their role to Admin.
     */
    public function updateUserRole(Request $request, User $user)
    {
        $this->requireAbility($request, 'user.change_role_platform', 'Only a Super Admin can perform this action.');
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own role.');
        abort_if($user->hasRole('Super Admin'), 422, 'Super Admin roles cannot be changed through this endpoint.');

        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        if ($data['role'] !== 'Admin') {
            $this->abortIfLastActiveAdmin($user, 'changing their role');
        }

        $user->update(['role' => $data['role'], 'roles' => [$data['role']]]);
        $this->log($request, 'Edit', "Changed {$user->name}'s role to {$data['role']}.");

        return $user->fresh();
    }

    public function registrationCode(Request $request, Barangay $barangay)
    {
        $this->requireAbility($request, 'barangay.view_registration_code', 'Only a Super Admin can perform this action.');
        // A registration code onboards staff; it does not authorize map-based
        // operations. Keep boundary review independent so a PSGC barangay can
        // be staffed while its reference outline is awaiting verification.

        $setting = RegistrationSetting::for($barangay->id);

        return response()->json(['staff_code' => $setting->staff_code, 'updated_at' => $setting->updated_at]);
    }

    public function regenerateRegistrationCode(Request $request, Barangay $barangay)
    {
        $this->requireAbility($request, 'barangay.regenerate_registration_code', 'Only a Super Admin can perform this action.');

        $setting = RegistrationSetting::regenerateFor($barangay->id);
        $this->log($request, 'Edit', "Regenerated the staff registration code for {$barangay->name}.");

        return response()->json(['staff_code' => $setting->staff_code, 'updated_at' => $setting->updated_at]);
    }

    /**
     * Unscoped by design — BelongsToBarangay's global scope only narrows a
     * query when the VIEWER has a barangay_id, which a Super Admin never
     * does. Every barangay's own Admin still only ever sees their own
     * barangay's entries through the same model, unchanged.
     *
     * Restricted to ACCOUNT_MODULES so fleet-detail entries (vehicles,
     * tickets, maintenance, etc.) logged by FleetController/TicketController
     * never leak through this endpoint — see this class's docblock.
     */
    public function activityLog(Request $request)
    {
        $this->requireAbility($request, 'activity_log.view_platform', 'Only a Super Admin can perform this action.');

        return ActivityLog::with('user')
            ->whereIn('module', self::ACCOUNT_MODULES)
            ->latest('log_id')
            ->limit(200)
            ->get();
    }

    /**
     * Public "Report a Concern" submissions from the Support Center page —
     * a barangay that looks unmanaged, or a suspected fake-staff account.
     * This is the inbox that closes the loop: the Super Admin reads a
     * report here, then acts using the tools above (promote/recover a
     * barangay, deactivate a suspicious account) and marks it resolved.
     */
    public function concernReports(Request $request)
    {
        $this->requireAbility($request, 'concern_report.view', 'Only a Super Admin can perform this action.');

        return ConcernReport::with('resolvedBy')->latest('id')->get();
    }

    public function resolveConcernReport(Request $request, ConcernReport $concernReport)
    {
        $this->requireAbility($request, 'concern_report.resolve', 'Only a Super Admin can perform this action.');

        $concernReport->update([
            'status' => 'Resolved',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);
        $this->log($request, 'Edit', "Marked concern report #{$concernReport->id} resolved.");

        return $concernReport->fresh('resolvedBy');
    }

    public function reopenConcernReport(Request $request, ConcernReport $concernReport)
    {
        $this->requireAbility($request, 'concern_report.reopen', 'Only a Super Admin can perform this action.');

        $concernReport->update(['status' => 'Open', 'resolved_by' => null, 'resolved_at' => null]);
        $this->log($request, 'Edit', "Reopened concern report #{$concernReport->id}.");

        return $concernReport->fresh();
    }

    /**
     * Permanently removes a concern report — the only way to clear out
     * obvious spam, since resolve/reopen only ever toggle status and never
     * delete. Submission is already rate-limited (see /concern-reports in
     * routes/api.php); this is the cleanup side of that.
     */
    public function destroyConcernReport(Request $request, ConcernReport $concernReport)
    {
        $this->requireAbility($request, 'concern_report.delete', 'Only a Super Admin can perform this action.');

        $id = $concernReport->id;
        $concernReport->delete();
        $this->log($request, 'Delete', "Deleted concern report #{$id}.");

        return response()->json(['message' => 'Concern report deleted.']);
    }
}
