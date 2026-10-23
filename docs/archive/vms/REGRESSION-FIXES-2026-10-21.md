# Regression fixes — 21 October 2026

All **22 findings** in the [regression audit](REGRESSION-REPORT-2026-10-21.md) now have local fixes. The original audit is retained as the before-change record. Cloud deployment acceptance remains separate from local verification.

## Changes and verification

| Finding | Fix | Verification |
|---|---|---|
| REG-01 | Logout sends its captured bearer token even after browser storage is cleared. The interceptor preserves an explicit Authorization header. | Chromium: remembered and session-only login both produce logout 200, then token replay 401. |
| REG-02 | Fault/maintenance type resolution looks up canonical names without inserting new shared catalog rows. Explicit catalog creation retains its permission checks. | API bypass probe now reports `catalogCreated: false`; backend checks cover schedule, issue, and proposal submissions. |
| REG-03 | SmartForm resets on actual initial-data changes rather than a newly allocated object's identity. Conditional fields preserve state and selected File objects. | Cost, notes, external selection, and receipt survive switching shop mode in both directions. |
| REG-04 | Removed the mount effect that overwrote the URL-selected module. Workspace state starts fresh when the account/roles change. | Schedule direct link and refresh both render details. |
| REG-06 | Calendar fields use Asia/Manila dates. Completion takes a fresh default when its dialog opens. | Browser midnight case defaults to 22 October; unit checks cover midnight and year rollover. |
| REG-07 | Receipt inputs advertise only jpg/jpeg/png/pdf. The cabinet rejects unsupported selections before submission. | GIF rejection preserves the already-selected valid receipt. |
| REG-08 | Catalog Add controls use each endpoint's creation ability. Reported-person creation retains its separate `issue.create` permission. | Custodian maintenance-type Add action is absent; backend catalog guards remain intact. |
| REG-09 | Document deletion/replacement deletes its managed object and revokes its file reference. | Storage fake confirms bytes removed; previous signed download returns 404 after deletion. |
| REG-10 | Uploads use a private bucket or private local root. Authorized responses issue five-minute signed URLs. Opening a saved file renews its link through authentication, tenant/role checks, and current repair assignment checks. | Unsigned access 403; signed retrieval 200; expired signature 403; renewal works; reassigned mechanic cannot renew previous repair access. |
| REG-13 | API guest handling no longer tries to generate a missing named login route. | Anonymous requests with HTML Accept or no JSON Accept return 401. |
| REG-14 | Removed unused legacy UI code and duplicate icon keys, corrected dependencies/error causes/ref rendering, and replaced state synchronization effects with guarded updates. External startup I/O can be cancelled before it begins. | ESLint: **0 errors, 0 warnings**. Rules were not disabled globally to obtain this result. |
| REG-15 | Public/workspace routes, map components, and file cabinets load on demand. | Production build passes without the chunk-size warning; the entry JavaScript is approximately 296 kB rather than 1,504 kB. No device-speed benchmark is claimed. |
| REG-17 | Policy section helpers use block containers for mixed prose/list content. | No `p ul`/`p ol` nesting and no nesting console errors in Chromium. |
| REG-18 | Both policy-card marks use the shared VMSLogo. | Shared SVG component is present on Privacy and Terms. |
| REG-19 | Privacy describes configured local/cloud databases, private storage, temporary links, and hosting providers. | Content reviewed against the implemented storage path; production configuration still requires verification. |
| REG-21 | Initial Province loading has a visible error and retry action; stale responses are ignored. | Forced failure followed by retry successfully repopulates the dropdown. |
| REG-22 | Location tabs/actions and the topbar location cascade wrap at narrow widths. Actual header height offsets page content. | Admin Vehicle Location: document width 390 px at viewport 390 px. |
| REG-23 | Registration-code controls stack/wrap within their card. | Admin Users: document width 390 px at viewport 390 px. |
| REG-24 | Table screen-reader descriptions are positioned relative to their avatar group, keeping their boxes inside the table's scroll container. | Custodian schedules: document width 390 px at viewport 390 px. |
| REG-25 | History toolbar/search controls wrap and shrink within their container. | Custodian Vehicle History: document width 390 px at viewport 390 px. |
| REG-26 | Removed the obsolete delegation checkbox and its form transformations. Registration remains a standard Custodian duty, matching the existing role model. Historical database flags are retained. | Admin edit screen no longer offers the ineffective control; backend role tests pass. |
| REG-27 | Support and README explain Custodian proposal, Admin approval/assignment, repair, and independent whole-ticket Custodian closure. Separate maintenance-record confirmation is distinguished. | Reviewed against the successful API lifecycle and current routes. |

## Validation evidence

- [Backend result](../../../logs/regression-fixes-backend.txt) and [JUnit](../../../logs/regression-fixes-junit.xml): **422 tests passed / 1,770 assertions**, including new security and migration checks.
- [Targeted Chromium assertions](../../../logs/regression-fixes-browser-checks.json): **27 passed**, no uncaught browser errors. Includes both token stores, links, form/file state, recovery, policies, all four mobile findings, and private-file opening/deletion.
- [Four-role browser smoke](../../../logs/regression-fixes-browser.json): 45 observations, no uncaught exceptions. Development impersonation-candidate 403s for non-Admin roles are expected.
- [API workflow](../../../logs/regression-fixes-api.json) and [follow-up](../../../logs/regression-fixes-api-followup.json): registration/approval, whole-ticket repair/closure, scheduling/recurrence, reports, and role denials passed. Catalog insertion probe is now false.
- [Ticket layout checks](../../../logs/regression-fixes-layout-verification.json): both methods at 1920/1440/1024/390 px, dark-theme sample, and two successful proposal submissions.
- [Registration browser check](../../../logs/regression-fixes-focused-browser.json): wrong-code error and successful staff registration. The short animation-time width samples are diagnostic observations, not additional settled layout failures.
- [ESLint](../../../logs/regression-fixes-eslint.json), [production build](../../../logs/regression-fixes-build.txt), and [frontend tests](../../../logs/regression-fixes-frontend-tests.txt): lint/build pass; seven geometry/calendar tests pass.
- [Machine-readable summary](../../../logs/regression-fixes-summary.json). Environment-specific setup and workflow scripts are retained in `logs/regression-fixes-harness/`; the targeted browser assertions are in `logs/regression-fixes-browser-checks.cjs`. These require an isolated QA database and must not be pointed at production.

## Storage rollout and local database

The new `managed_files` migration was applied locally after creating a SQLite backup at `%TEMP%/vms-before-regression-fixes-b5c48c3f-22ef-44f8-8eeb-426970bfb47d.sqlite`. Only the new schema migration was applied; the unrelated pending boundary migration was left alone. The legacy-media inventory found **zero referenced public files** in this working database, so no working upload objects were moved or deleted. Browser/API mutations used a separate QA snapshot.

For another installation or deployment:

1. Apply the new migration.
2. Configure `SUPABASE_PRIVATE_BUCKET` to an actual **private** bucket, together with the existing Supabase credentials. An unset/unavailable bucket uses `storage/app/private`; that directory needs persistent storage on a deployed host.
3. Run `php artisan media:privatize` to inventory referenced legacy uploads. Add `--apply` to copy and verify their bytes before removing public copies. Use `--include-cloud` to explicitly include the old `SUPABASE_BUCKET`/`SUPABASE_URL`. The command retains original database references through a mapping and rechecks copies on retries.
4. Verify uploads, retrieval, expiry, deletion, bucket policy, and backup/restoration on the target host. This session did not change or inspect a live Supabase bucket.

Signed links grant temporary access to their holder until expiry; renewal requires an active authenticated account and the applicable scope checks. Permanent public upload URLs are no longer generated.

The earlier Mantalongon reference-boundary accuracy concern (historical REG-20, excluded from the 22-item audit count) still requires authoritative geographic evidence. These code fixes do not establish a surveyed boundary or replace deployment/user acceptance.
