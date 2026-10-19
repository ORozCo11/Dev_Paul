# Barangay boundary data governance

## Approved operational source

VMS identifies every selectable barangay by its PSA Philippine Standard
Geographic Code (PSGC), then retrieves geometry only by that code from the
bundled Philippines COD-AB v03 ADM4 reference. The source is attributed to
NAMRIA and PSA, distributed by OCHA Philippines through HDX:

- Dataset: <https://data.humdata.org/dataset/cod-ab-phl>
- License: [CC BY-IGO 3.0](https://creativecommons.org/licenses/by/3.0/igo/legalcode)
- Bundled resource metadata: `backend-api/database/data/barangay-geography-sources.json`
- Reproducible conversion and attribution: `backend-api/database/data/barangay-boundaries-detailed/ATTRIBUTION.md`

The imported geometry is WGS84 GeoJSON longitude/latitude. The import retains
Polygon and MultiPolygon parts, holes, and source vertices; it does not
generate, geocode, or geometrically simplify a replacement boundary.

## Accuracy and legal-status limits

COD-AB is an authoritative-source *reference* dataset, not proof of a current
legal or cadastral boundary. Its bundled v03 records were sampled with
`valid_on: 2025-02-13`; the downloaded resource was last modified 2026-05-28.
PSGC is a geographic classification, not a boundary-survey authority. A PSA
PSGC update can change a name or code without proving that a polygon remains
current.

VMS therefore marks an automatically matched reference outline as **Pending
Verification** in the interface (`needs_review` in the current persisted
schema). It may be displayed for operational context, but it is not called a
legally verified boundary. **Verified** is reserved for a Super Admin’s
explicit confirmation of an uploaded LGU/NAMRIA source document that names its
provenance. No geometry match produces Verified automatically. If the
PSGC-keyed reference has no geometry, VMS reports **Unavailable** and does not
invent or substitute another outline.

## Evaluated but not used as automatic fallbacks

- PSA PSGC API: official identifier/name hierarchy; it requires an access
  token and provides geographic classification rather than the boundary
  geometry used by this application.
- GeoRiskPH PSA Barangay Boundary service: its service description says the
  layer is **indicative**, observed at the end of June 2016, and based on the
  2015 census layer. It is unsuitable for a current or legally verified
  fallback.
- NAMRIA/Geoportal Philippines: preferred when an LGU/NAMRIA-approved current
  boundary and explicit reuse permission are supplied, but no public
  application endpoint/terms were found that supports silently replacing a
  VMS boundary. Such geometry must be reviewed and uploaded with its source
  document.
- OpenStreetMap/Nominatim: not an authoritative boundary source; VMS does not
  query it for barangay registration or boundary refresh.

## Review and operational safeguards

Before acceptance, VMS validates Polygon/MultiPolygon type, closed rings,
coordinate order and geographic range, holes, and municipality-level
alignment. It renders the original GeoJSON in Leaflet and fits the displayed
geometry. Point-in-polygon checks preserve holes and separate MultiPolygon
parts. A location near a contested or GPS-uncertain edge must be treated as a
review flag rather than definitive jurisdiction evidence.

The regression suite includes the PSGC-keyed geometry flow, unknown/missing
geometry, duplicate barangay names in different cities, geometry holes, and
MultiPolygons. Jubay, Liloan, Cebu (`0702227005`) remains a required release
fixture whenever the reference dataset is refreshed.
