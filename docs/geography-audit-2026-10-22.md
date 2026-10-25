# Registration geography audit — 22 October 2026

The bundled catalog is **not complete/current against PSA's 30 September 2026 PSGC**. Internal database/API coverage and official currency are separate checks.

| Geographic level | Bundled catalog | Official PSA summary |
| --- | ---: | ---: |
| Real provinces | 81 | 82 |
| UI province groups | 84 (81 provinces + 3 artificial groups) | Not an official geographic level |
| Cities and municipalities | 1,634 | 1,642 (149 cities + 1,493 municipalities) |
| Barangays | 42,046 | 42,010 |

Sources: [PSA summary](https://psa.gov.ph/classification/psgc/summary), [PSA latest publication](https://psa.gov.ph/classification/psgc), [BARMM municipality list](https://psa.gov.ph/classification/psgc/municipalities/1900000000).

## Confirmed discrepancies

- The catalog contains the former **Maguindanao**, rather than **Maguindanao del Norte** and **Maguindanao del Sur**. Both current names are absent.
- These eight municipalities are absent: Kapalawan, Old Kaabakan, Kadayangan, Nabalawag, Pahamuddin, Malidegao, Ligawasan, and Tugunan. Their official codes are recorded in `backend-api/database/data/psgc-official-summary.json`.
- The net barangay difference is 36. This does **not** identify which rows are obsolete, missing, renamed, or attached to the wrong municipality. Equal counts would also not establish correctness.
- The UI groups Metro Manila, Cotabato City, and Isabela City are artificial grouping entries, not additional provinces.

## Verification and limitations

Run `python backend-api/scripts/audit-registration-geography.py` with the API running on port 8001. It checks bundled code uniqueness, parent coverage, populated city groups, all database identities/parents, and all 1,719 registration dropdown responses. The JSON report in `logs/registration-geography-audit.json` distinguishes internal consistency from official freshness; a known official discrepancy makes the overall audit fail.

The official XLSX masterlist download returned HTTP 403 in this environment. No connected browser was available. The [official PSA API documentation](https://psa.gov.ph/classifications-api/psgc) requires an access token. Therefore a full row-by-row comparison of current official names, codes, and parent assignments remains unverified. The official totals and named discrepancies above were verified on PSA's public pages.

The bundled reference data has been preserved. A safe update needs the official masterlist and a reviewed correspondence-code migration that retains existing barangay IDs, tenant accounts, work records, and verified boundaries. Reassignments and retired codes must be reconciled explicitly; adding eight empty municipality names or deleting 36 arbitrary barangays would not resolve the discrepancy.

Boundary polygon coverage is separate from selectable-location coverage; the existing source metadata reports 40,771 matched detailed polygons and 1,275 unmatched catalog barangays. A selectable barangay does not imply a verified current legal boundary.
