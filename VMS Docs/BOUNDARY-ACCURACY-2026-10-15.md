# Boundary accuracy follow-up — 15 October 2026

## Outcome and unresolved data

The application no longer automatically treats imported barangay reference polygons as verified boundaries. **The exact current Mantalongon boundary has not been independently established or redrawn.** A code match, higher vertex count, or agreement between sources using the same historical geometry does not establish geographic accuracy. Earlier reports of a “fixed boundary” overstated that conclusion.

Reported case: Cebu → Dalaguete → Mantalongon, tenant ID 34411, PSGC 0702222024. The saved polygon, bundled detailed source, and government-hosted [PSA GeoRisk reference layer](https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4) each contain 33 outer-ring vertices. It is the underlying reference shape, not a lower-detail frontend rendering. The selected barangay ID and code are correct; typing a street address during registration does not create or reposition its polygon.

Online checks found Mantalongon Barangay Hall, Mantalongon Elementary School, Mantalongon National High School, and DepEd's Sampig Elementary School coordinate inside the Mantalongon reference. Tabon Elementary School's official coordinate is inside the neighboring Tabon reference. These checks do not verify every edge. The PSA GeoRisk layer describes indicative 2016 boundaries. The [DA/BSWM Dalaguete map](https://www.bswm.da.gov.ph/npaaad%26safdz/wp-content/uploads/2025/06/CEBU-SAFDZ_Dalaguete.pdf) explicitly states that political boundaries are not authoritative. No current LGU-approved replacement geometry was located during this follow-up.

## Application fixes

- Registration attaches a matched imported outline as `needs_review`, with no verification timestamp or verifying administrator, and stages it for review. A reviewed replacement remains in place for later registrations.
- Public boundary responses include `boundary_status` and `boundary_verified`. Old automatic assignments cannot claim verification merely because an earlier import saved `verified`.
- The fleet and add-location maps show an unverified-reference notice. Unreviewed polygons do not reject a valid location pin solely for falling outside their outline; the user must check the address and coordinates. Reviewed boundaries continue to enforce containment. Coordinate range/finite-value checks remain active.
- Super Admin → Barangay Management → View / Manage → **Review / Replace Boundary** accepts a single WGS84 GeoJSON Polygon, MultiPolygon, Feature, or single-feature FeatureCollection. A source document title or URL is required. The client accepts files up to 5MB; the API allows at most 50,000 vertices. It checks closed, nondegenerate rings, holes, finite/ranged coordinates, and mismatching PSGC metadata. This is basic shape validation, not a complete topological or cadastral validation.
- **Save for review** stages the replacement and records its source in the audit log. **Confirm Boundary** applies it and records the reviewing administrator and timestamp. Staging or rejecting a replacement preserves the live geometry. Ordinary Admin, Custodian, and Maintenance Personnel accounts cannot replace platform boundaries.
- Refresh/discard actions cannot turn an automatic reference into a verified boundary without confirmation.

## Local repair and verification

Before repair, a SQLite backup was created at the user's temporary directory as `vms-before-reference-review-20261015.sqlite`. `php artisan geography:refresh-boundaries` marked the two automatic assignments (Suba and Mantalongon) for review. All live polygon coordinates remained unchanged, as did all 9 users, 15 vehicles, 18 tickets, and 30 registration settings. Existing legacy or administrator-confirmed geometry was preserved. [Integrity evidence](../logs/boundary-review-data-integrity.json).

**409 backend tests passed, 1,676 assertions.** Build and focused lint passed. Six frontend geometry tests cover holes, islands, label placement, invalid coordinates, and reference-versus-reviewed location behavior. Browser verification exercised replacement-file preview, required source input, staging before confirmation, and the Mantalongon map's unverified notice, with no runtime errors. Upload requests in the browser harness were mocked; API permissions, validation, confirmation, and future-registration preservation were exercised by backend tests.

- [Backend suite JUnit](../logs/boundary-review-junit.xml)
- [Focused review tests](../logs/boundary-review-focused-junit.xml)
- [Mantalongon map](../logs/mantalongon-reference-review.png)
- [Replacement review screen](../logs/boundary-replacement-review.png)
- [Browser evidence](../logs/boundary-replacement-browser.json)

To replace the geographic outline accurately, the remaining input is a current LGU-approved polygon or a boundary map that can be georeferenced and checked. The application is ready to review and apply that geometry without recreating accounts, changing registration codes, or relying on place-label positions.
