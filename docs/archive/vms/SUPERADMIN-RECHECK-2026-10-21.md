# Super Admin recheck — 21 October 2026

**Fix follow-up:** SA-R01 was subsequently fixed and the full 104-scenario set passed. See [the fix and recheck evidence](SUPERADMIN-REFERENCE-SEARCH-FIX-2026-10-21.md). The results below preserve the earlier audit snapshot.

**Result: one new Low-severity finding.** All 98 scenarios from the prior fix verification passed again; six additional probes produced five passes and one confirmed search discrepancy. No application fixes were made during this recheck.

## New finding: SA-R01 — Displayed concern reference cannot be searched

**Severity:** Low. **Status:** Fixed and rechecked; see the follow-up above.

1. Submit a concern and open Super Admin → Concern Reports.
2. Find it using its barangay name; note its displayed reference, such as `CR-3`.
3. Replace the search text with that exact reference.

**Actual:** The report disappears and the page says `No matching concerns`, despite the empty-state guidance suggesting searching by reference. In the isolated fixture, searching `QA Reference Search` found the report, while `CR-3` found no rows.

**Expected:** Searching the displayed reference finds the same concern, with case-insensitive matching consistent with other search fields.

**Cause:** The search joins the numeric `report.id` with description, barangay, reporter and type, while the table displays `CR-{id}`. The searchable string never includes the `CR-` prefix.

**Code:** `frontend-spa/src/views/SuperAdminPanels.jsx:167` (search), `:170` (display).

**Evidence:** [additional browser checks](../../../logs/superadmin-recheck-extra.json), [screenshot](../../../logs/superadmin-recheck-concern-reference.png). A fix should include the displayed reference in the searchable fields and retain a browser assertion for exact-reference lookup.

## Verification completed

- **433 backend tests passed, 1,825 assertions.** [Output](../../../logs/superadmin-recheck-backend.txt), [JUnit](../../../logs/superadmin-recheck-junit.xml).
- **104 API/browser scenarios: 103 passed, 1 failed** for SA-R01. Original 98-scenario set: all passed.
- **7 frontend tests passed.** [Output](../../../logs/superadmin-recheck-frontend-tests.txt).
- **Lint: zero errors and warnings; production build passed.** [Lint](../../../logs/superadmin-recheck-eslint.json), [build](../../../logs/superadmin-recheck-build.txt).
- No browser runtime exceptions. All eight pages passed the existing desktop/mobile overflow checks in light and dark themes.

The rerun covered onboarding/approvals, provisioning persistence, role safeguards, legacy roles, full activity search/pagination, account recovery, boundary staging/confirmation, registration codes, concern lifecycle, notifications, impersonation/return, passwords/logout, invalid routes, HTTP failure/retry, and stalled unrelated endpoints. Additional probes checked role-change confirmation cancellation, deactivation cancellation, browser Back from barangay detail, clearing an empty activity search, and concern-reference lookup.

The prior nine fixes remain verified within these scenarios. This new finding was outside the earlier set; the previous passing result did not establish exhaustive coverage.

## Isolation and evidence

Tests used a fresh snapshot at `%TEMP%/vms-superadmin-recheck-20261021/regression.sqlite`, API port 8024, and frontend port 5194. The working database's SHA-256 hash was unchanged before/after. No live account or fleet mutations, migrations, commits, or deployment were performed. The isolated servers were stopped after the recheck.

[Machine-readable summary](../../../logs/superadmin-recheck-summary.json), [API checks](../../../logs/superadmin-recheck-api.json), [main browser checks](../../../logs/superadmin-recheck-browser.json), [additional workflows](../../../logs/superadmin-recheck-corners.json), [stalled-request checks](../../../logs/superadmin-recheck-latency.json).

This remains local SQLite/Chromium verification. Production PostgreSQL, external email delivery, map accuracy, load stress, and exhaustive accessibility were outside this recheck.
