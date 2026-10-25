# Philippine administrative boundary reference

Source: National Mapping and Resource Information Authority (NAMRIA) and Philippine Statistics Authority (PSA), distributed by OCHA Philippines through HDX as Philippines COD-AB v03.

Dataset: https://data.humdata.org/dataset/cod-ab-phl

Downloaded SHP resource: https://data.humdata.org/dataset/caf116df-f984-4deb-85ca-41b349d3f313/resource/4e4067d6-3fc8-4d2c-91ea-330c3d4f8e8e/download/phl_admin_boundaries.shp.zip

License: Creative Commons Attribution 3.0 IGO (CC BY-IGO): https://creativecommons.org/licenses/by/3.0/igo/legalcode

Retrieved 15 October 2026. The resource was last modified 28 May 2026; the sampled ADM4 records identify version v03 and valid_on 20250213. Package summary dates differ; these are not a claim of a 2026 survey or current legal/cadastral accuracy.

Changes: extracted ADM4 Polygon/MultiPolygon geometry into gzip city shards, joined codes through the bundled PSGC correspondence catalog, converted shapefile winding to GeoJSON winding, retained holes and every source vertex, and rounded coordinates to six decimal places (about 0.1m). No Douglas–Peucker simplification is applied. Unmapped or duplicate codes are excluded. These modifications are made by this application, not endorsed by the source organizations.

See ../barangay-geography-sources.json for checksums and coverage; ../../../scripts/import-boundary-shapefile.cjs reproduces conversion from the extracted SHP and DBF files.
