# Super Admin fixes and final regression — 21 October 2026

**Later recheck:** expanded checks found a Low-severity concern-reference search issue, which was subsequently fixed. The full 104-scenario set now passes; see [the latest fix verification](SUPERADMIN-REFERENCE-SEARCH-FIX-2026-10-21.md). The results below describe the original fix verification.

**All nine findings from the [Super Admin audit](SUPERADMIN-REGRESSION-REPORT-2026-10-21.md) are fixed locally. No unexpected failures remain in the final tested workflows.** Additional edge cases found during retesting were fixed as described below.

## Changes

| Finding | Resolution |
| --- | --- |
| SA-01: Added barangay disappears | Persist `provisioned_at`; management includes explicitly provisioned PSGC rows before their first user or registration code. Repeated provisioning preserves the original timestamp and reports actual staff/admin counts. |
| SA-02: Role changes strand assigned work | Both platform and tenant role editing block removal of roles required for assigned open work. Scheduled and Pending Approval schedules are protected. Orphan recovery adds Admin while preserving existing roles and responsibilities, and approves/activates pending or inactive accounts. |
| SA-03: Activity cutoff | The portal uses server-side search and pagination over all authorized activity, with server-side scope filtering. Fleet modules remain excluded. Search preserves literal, case-insensitive substring behavior, including `%`, `_`, and `!`. |
| SA-04: Cannot remove secondary role | Save compares the complete effective role set; retaining the primary role while removing secondary roles works when assignment safeguards permit it. |
| SA-05: Role filter hides legacy accounts | Display, filters, and role editing include the primary role alongside the stored roles array, including empty arrays. |
| SA-06: One failed endpoint blocks all pages | Each page waits for its own resources. Successful resources become available independently; failed resources have relevant retry controls. Stalled concerns/support requests do not block Users. Requests have a timeout, and superseded responses cannot overwrite newer refreshes. |
| SA-07: Silent geography failures | Add Barangay shows loading/error states and working retries for province, city, and barangay lookups; dependent options reset when their parent selection changes. |
| SA-08: Boundary/code discrepancy | Interface guidance and API messages match the existing tested rule: a live imported outline supports code issuance; missing outlines and pending replacements block it. Uploaded-source confirmation marks a boundary reviewed. |
| SA-09: Unknown routes show Settings | Invalid tabs and invalid nested paths display a not-found state with a return control. |

Retesting also corrected reviewed-boundary status in provisioning/refresh responses, the boundary lookup success message, and the notification bell's accessible name when an unread count is present. Account and notification controls expose their expanded state.

## Final verification

[Machine-readable final result](../../../logs/superadmin-fixes-summary.json).

- **433 backend tests passed, 1,825 assertions**, including 11 new regression tests covering provisioning, role/work safeguards, recovery, complete activity search, and code/boundary rules. [Result](../../../logs/superadmin-fixes-backend.txt), [JUnit](../../../logs/superadmin-fixes-junit.xml).
- **98 API/browser scenarios passed**: [37 API checks](../../../logs/superadmin-fixes-api.json), [51 main browser checks](../../../logs/superadmin-fixes-browser.json), [8 additional workflow checks](../../../logs/superadmin-fixes-corners.json), and [2 stalled-request checks](../../../logs/superadmin-fixes-latency.json).
- **7 frontend tests passed**. [Result](../../../logs/superadmin-fixes-frontend-tests.txt).
- **ESLint: 0 errors, 0 warnings**; production build passed. [Lint](../../../logs/superadmin-fixes-eslint.json), [build](../../../logs/superadmin-fixes-build.txt).
- No browser runtime exceptions or document-level horizontal overflow across all eight pages at 1440×1000 and 390×844, in both light and dark themes.
- New migration rollback/reapply checked on a separate snapshot, preserving all 42,048 barangay rows in that QA snapshot.

The final isolated run exercised first-Administrator registration → pending login denial → approval → successful login; staff approval/rejection; account activation/deactivation; orphan recovery; role changes and assigned-work rejection; boundary upload/preview/staging/discard/confirmation; registration code rotation; concern resolve/reopen/delete; activity search beyond 200 entries and pagination; notifications; impersonation and return; password mismatch/update/restore; logout and protected-route redirect; request failures and retries; invalid routes; and stalled unrelated requests.

Screenshots: [provisioned barangay retained](../../../logs/superadmin-fixes-provisioned.png), [secondary role removed](../../../logs/superadmin-fixes-role.png), [older activity found](../../../logs/superadmin-fixes-history.png), [reviewed boundary](../../../logs/superadmin-fixes-boundary.png), [geography retry](../../../logs/superadmin-fixes-provinces-retry.png).

## Database and rerun notes

The new migration is `backend-api/database/migrations/2026_10_21_170000_add_provisioned_at_to_barangays.php`. It was applied to the local workspace database after a SQLite backup at `%TEMP%/vms-before-superadmin-fixes-20261021-121647.sqlite`. Only this new migration was applied; unrelated pending migrations were left untouched. Target deployments must apply this migration with the code change.

Browser/API mutations used `%TEMP%/vms-superadmin-fixes-20261021/regression.sqlite`, not the working database. Preparation and local-only harnesses are under `logs/superadmin-fixes-*`. To repeat: run the scratch preparation, setup, and history fixtures, start the isolated router on 8023 and Vite on 5193, then run API → main browser → additional workflows → latency checks. The scripts use local paths and the existing Playwright installation; recreate the QA snapshot before a complete rerun.

Local verification confirmed unchanged working-database row counts (9 users, 42,046 barangays, 15 vehicles) and zero QA accounts. The isolated API and Vite servers were stopped after verification.

This is local regression evidence. PostgreSQL deployment behavior, real email delivery, current legal boundary accuracy, production load, and a complete accessibility audit were outside this verification. The unrelated Mantalongon boundary accuracy question remains documented separately; these fixes do not certify map accuracy. Existing unrelated working-tree changes were preserved, and no deployment or commit was performed.
