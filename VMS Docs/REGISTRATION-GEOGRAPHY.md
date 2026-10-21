# Registration geography — 15 October 2026

**Latest accuracy follow-up:** imported outlines are staged as unverified references and require review. Agreement with historical datasets does not establish current boundary accuracy. Super Admins can upload and confirm a replacement for existing and future accounts. Mantalongon's exact current outline remains unresolved. See [boundary accuracy follow-up](BOUNDARY-ACCURACY-2026-10-15.md); it supersedes any earlier implication that importing detailed polygons verifies geographic accuracy.

Registration now selects a preloaded Province → City/Municipality → Barangay hierarchy. Public registration cannot create a barangay from text. The API requires a coded reference ID, checks that it belongs to the selected city, and checks the province/city relationship when a province is supplied. Staff registration codes and approval requirements still apply.

The bundled reference contains **42,046 barangays**, including **80 for Cebu City**. Responses are scoped to the chosen city; late responses from older selections are ignored. A failed lookup offers a retry instead of replacing the selector with a text field. An empty reference remains unavailable for selection.

## Boundary identity and coverage

Barangays have a unique 10-digit `psgc_code`. Boundary files are matched by that code, never by a bare name. Banilad in Cebu City (`0730600007`) and Banilad in Mandaue City (`0731300003`) therefore resolve to different geometries and different tenant IDs.

On successful registration, a missing boundary is attached from the detailed bundled reference when an unambiguous match exists. Automatically assigned coarse references are upgraded; administrator-confirmed or other manually supplied boundaries are preserved. The public boundary endpoint can also resolve the reference geometry before the first account registers. The workspace defaults to the account's actual province, city, and barangay; the city selector supports multiple registered cities in one province.

**40,771 references have a matched detailed Polygon/MultiPolygon; 1,275 do not.** Unmapped codes are excluded; geographic correspondence codes are translated through the explicit catalog mapping, including highly urbanized cities. Missing geometry stays missing: it is not substituted with Paknaan or a same-named barangay. The map shows an unavailable-boundary notice and a country-level frame; location entry waits for a boundary to be supplied/reviewed.

These are **full-detail source reference polygons**, with every source vertex retained and coordinates stored to six decimal places. That is storage precision (about 0.1m), not positional accuracy: the outlines are only as accurate as the indicative source mapping. They are not cadastral survey boundaries or a guarantee of current legal boundaries. Polygon holes and separate islands are retained in map rendering and location containment checks. Areas without a match still require the existing Super Admin boundary-review flow or an updated vetted dataset.

## Sources and provenance

- Barangay list: [PSGC API mirror](https://psgc.gitlab.io/api/barangays/), consistent with the existing bundled city correspondence codes. This is a bundled snapshot, not a live dependency or a claim of the latest PSA quarter.
- Boundary source: [HDX Philippines COD-AB](https://data.humdata.org/dataset/cod-ab-phl), PSA/NAMRIA data distributed by OCHA Philippines, v03. Sampled records carry valid_on 2025-02-13; the resource was updated 2026-05-28. Package summary dates differ, so this does not establish a 2026 survey date. License: [CC BY-IGO 3.0](https://creativecommons.org/licenses/by/3.0/igo/legalcode). SHP/DBF checksums and conversion details are recorded in the source manifest. The prior 0.005-degree simplified dataset is retained as legacy provenance.
- Official geographic classification reference: [PSA PSGC API documentation](https://psa.gov.ph/classifications-api/psgc). Its token-based, versioned API is separate from the bundled mirror used here.

Machine-readable provenance, coverage counts, and the upstream SHA-256 are in `backend-api/database/data/barangay-geography-sources.json`. Boundary shards are compressed by correspondence-code prefix to avoid decoding the entire country for one request. The upstream license is retained under `database/data/barangay-boundaries/LICENSE.txt`.

## Existing data and duplicate names

The import preserves existing barangay IDs, names, city associations, boundary geometry, staff registration codes, and linked accounts. Matching legacy Mandaue spellings such as Paknaan/Pakna-an and Centro variants retain their original IDs. The legacy Mandaue seeder now includes the city in its lookup, preventing it from relocating another city's same-named record.

Some official barangays within one city share a name. Their reference names include a PSGC-code suffix to distinguish them without merging identities or violating the existing city/name constraint. Unmatched legacy free-text rows remain stored for existing accounts; they are not offered as public registration references.

## Installation and deployment

From `backend-api`:

```powershell
php artisan migrate --force
php artisan geography:sync
php artisan geography:refresh-boundaries
```

The new migration adds a nullable, unique PSGC column. The sync loads the bundled province/city/barangay reference without network calls and is repeatable. It does not run the demo-data seeders. Fresh `DatabaseSeeder` runs also include the reference import. New barangay offices still need a staff code provisioned through Super Admin's existing registration-code tools.

Applied to the local database during this change. A read-only SQLite backup was taken first at `C:/Users/Narra-Admin/AppData/Local/Temp/vms-before-registration-geography.sqlite`.

Local before/after verification found users, vehicles, maintenance tickets, and registration settings unchanged, including existing code values. Existing barangay ID/name/city/boundary fields were preserved. See [data-integrity results](../logs/registration-geography-data-integrity.json). This does not apply the migration or reference import to a remote deployment.

## Validation

- **403 backend tests passed; 1,622 assertions.** [Result](../logs/registration-geography-backend.txt), [JUnit](../logs/registration-geography-junit.xml).
- Six new feature tests cover free-text rejection, wrong parent/reference rejection, duplicate names across cities, code-matched boundaries, registration/approval behavior, missing boundaries, and repeatable nationwide import preserving tenant IDs and existing geometry/codes.
- Existing registration fixtures were updated to represent preloaded coded barangays. The geographic tests use process isolation, and PHPUnit's test-only memory allowance is 256 MB for the full reference/spreadsheet test batch; production PHP limits are unchanged.
- Frontend production build passed. Focused ESLint checks for Register and AddLocationMap passed; the earlier repository-wide lint findings are not claimed resolved.
- Chromium: successful Cebu City/Banilad first-Admin registration → approval → login; matching city/barangay/map defaults; all 80 Cebu City options; stale province response ignored; empty lists do not permit free text; retry restores the selector after a simulated 503.
- [Browser results](../logs/registration-geography-browser.json), [retry/map results](../logs/registration-geography-retry-and-map.json), [registration screenshot](../logs/registration-geography-cebu.png), [map screenshot](../logs/registration-geography-map.png).

PostgreSQL execution and current legal boundary accuracy were not independently verified in this local run.

## Boundary detail correction — 15 October 2026

Reported case: Albert Reboquio, Cebu → Bantayan → Suba (Pob.), PSGC 0702209022, tenant ID 34076. The stored automatic 2023 reference was a triangle: upstream simplification tolerance 0.005 degrees (roughly 550m). This removed coastal and street-level detail. It was replaced by a detailed source outline with **1,281 vertices**. New registrations use the same detailed reference service. Missing detailed matches stay unavailable rather than reusing the coarse shape.

The read endpoint resolves the replacement even before the deployment repair command runs. The repair command changes only automatic legacy references with no confirming administrator; manually confirmed boundaries are preserved. A local SQLite backup was created in the user's temporary directory as `vms-before-boundary-detail-20261015.sqlite` before repair. One existing automatic boundary (Suba) was updated; all 8 users, 15 vehicles, 18 tickets, and 29 registration settings remained equivalent at row level. [Integrity evidence](../logs/boundary-detail-data-integrity.json).

Validation: **406 backend tests passed, 1,649 assertions** ([JUnit](../logs/boundary-detail-junit.xml)); three frontend geometry tests passed for holes, disjoint islands, and invalid data. Registration tests cover upgrading Suba and preserving administrator-confirmed geometry. Chromium rendered the actual fleet map component with the detailed Suba geometry, with no runtime errors: [browser evidence](../logs/boundary-detail-browser.json), [map screenshot](../logs/boundary-suba-detailed.png). Production build passed. The existing LocationDensityMap line 359 lint finding remains in the earlier lint ledger.

Detailed data is sharded by city to keep request memory bounded. A read of the largest compressed city shard used 100MB peak with PHP's 128MB limit. Boundary assets total approximately 84MB compressed and are served by the backend, not bundled into frontend JavaScript. Run `node scripts/import-boundary-shapefile.cjs ADM4.shp ADM4.dbf database/data/barangay-boundaries-detailed` to reproduce from the documented source.

## Suba boundary and satellite-label verification — 15 October 2026

Follow-up: the satellite provider prints “Suba Pob.” north of the selected outline. Increasing the outline detail did not establish whether that label or the polygon was geographically correct. We checked the [government-hosted PSA barangay boundary layer on GeoRisk Philippines](https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4). It describes **indicative boundaries at June 2016**, generated from 2015 census mapping; it is not proof of current legal limits.

The government layer's Suba feature has 1,281 vertices. Its unique vertices match the imported Suba geometry at six decimal places; the maximum nearest-vertex discrepancy is about 0.078m, attributable to coordinate rounding. Its Binaobao polygon covers the northern coastal vicinity indicated in the screenshot. Suba Barangay Hall and Saint Paul Academy positions from OpenStreetMap fall inside the southern Suba reference polygon. The [DepEd school masterlist](https://www.deped.gov.ph/wp-content/uploads/2021/06/SY-2020-2021-Masterlist-of-Schools-Address-only-1-1.pdf) independently lists Saint Paul Academy at Pres. M.L. Quezon, Suba, Bantayan. These checks support the reference polygon, while the imagery provider's place label disagrees with it. They do not justify shifting the boundary to a screenshot rectangle.

No boundary or account data was changed during this follow-up. Registration continues to resolve the same PSGC-matched reference. Satellite provider place labels now default off, with a Map labels toggle to show them. The fleet map and add-location map put the selected barangay's own reference label inside its polygon, avoiding holes. Street-map labels are embedded in their tiles and remain provider-supplied.

Evidence: [verification JSON](../logs/suba-boundary-verification.json), [PSA reference GeoJSON](../logs/bantayan-psa-reference-comparison.geojson), [interactive comparison](../logs/suba-boundary-comparison.html), [comparison screenshot](../logs/suba-boundary-comparison.png), [updated app screenshot](../logs/boundary-suba-labels-fixed.png), [browser checks](../logs/boundary-labels-browser.json). Build, focused lint, and all five geometry tests pass. The existing broader lint debt remains unchanged.
