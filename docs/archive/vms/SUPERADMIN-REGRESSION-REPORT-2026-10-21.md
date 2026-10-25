# Super Admin regression audit — 21 October 2026

**Status update: all nine findings were subsequently fixed and retested locally.** See [fixes and final verification](SUPERADMIN-FIXES-2026-10-21.md).

**Original audit result: 9 confirmed findings: 1 High, 6 Medium, 2 Low.** The findings below describe the pre-fix snapshot. They are distinct from the earlier full-system audit and were reproduced against the working tree including its prior fixes.

## Method and scope

- Isolated SQLite snapshot and QA accounts; API at `127.0.0.1:8022`, frontend at `127.0.0.1:5192`, `APP_ENV=testing`. Mutations never targeted the working application database.
- Chromium at 1440×1000 and 390×844; all eight pages: overview, barangays, pending approvals, users, registration codes, concerns, activity, settings.
- 64 recorded API/browser checks, including successful workflows, negative cases, and controlled HTTP 500 fault injection. These are scenario observations, not 64 passing assertions.
- **55 existing targeted backend tests passed, 205 assertions.** Command: `php artisan test --filter='SuperAdmin|Impersonation|BarangayBoundary|LastAdmin|UserApproval'`.
- No browser runtime exceptions or document-level horizontal overflow on the eight pages at either tested viewport.
- No application fixes, commits, deployment, or migrations performed for this audit.

| ID | Severity | Page/workflow | Confirmed issue |
| --- | --- | --- | --- |
| SA-01 | High | Barangay Management | Added barangay immediately disappears despite success |
| SA-02 | Medium | Users / role changes | Removing an operational role strands assigned scheduled work |
| SA-03 | Medium | Activity Log | History silently stops at 200 entries; search cannot find older records |
| SA-04 | Medium | Users / Edit Role | Cannot retain primary role while removing secondary roles |
| SA-05 | Medium | Users / role filter | Valid legacy account disappears from its own role filter |
| SA-06 | Medium | All pages / loading | One failed data endpoint blocks unrelated administration pages |
| SA-07 | Medium | Add Barangay | Geography lookup failures leave an empty form without an error or retry |
| SA-08 | Low | Boundaries / registration codes | Review requirement in interface/messages conflicts with code issuance |
| SA-09 | Low | Routing | Unknown Super Admin routes display password settings |

## SA-01 — Added barangay disappears immediately

**Reproduce:** Open Barangay Management → Add Barangay → Ilocos Norte → Adams → Adams (Pob.) → Add. Search for `Adams (Pob.)` after the success notice.

**Actual:** API returns HTTP 201 and the page says `Adams (Pob.) (Adams) added.` Management immediately says `No barangays found`. API listing has 31 rows before and after, and excludes the newly provisioned reference ID 12556. Reloading does not restore it. This was reproduced both through direct API calls and the actual form.

**Expected:** Successful provisioning durably includes the barangay in management, overview counts, and the boundary/recovery workflow before its first user registers.

**Cause:** `SuperAdminController::storeBarangay()` attaches a reference boundary and logs the action, but does not persist a provisioning marker or registration setting. `barangays()` only includes rows without a PSGC code or with users/registration settings. A newly provisioned official PSGC row meets none of those conditions. Generating a code separately may make some rows reappear, but this is not a reliable recovery path for rows with unavailable boundaries.

**Relevant code:** `backend-api/app/Http/Controllers/SuperAdminController.php:73`, `:131`; `frontend-spa/src/views/SuperAdminWorkspace.jsx:580`.

**Evidence:** [API follow-up](../../../logs/superadmin-audit-api-followup.json), [browser workflows](../../../logs/superadmin-audit-workflows.json), [success plus missing row screenshot](../../../logs/superadmin-audit-provision-disappears.png).

**Fix direction:** Persist provisioning explicitly and include that state in management queries; cover provision → list → reload with a regression test.

## SA-02 — Role change bypasses the assigned-work safeguard

**Reproduce:** With a Scheduled maintenance assigned to a Maintenance Personnel account, attempt deactivation, then change that user's role to Custodian through Super Admin.

**Actual:** Deactivation correctly returns 422 for open work. Role change returns 200, leaving Schedule #14 Scheduled and assigned to account #12, whose sole role is now Custodian. The same account's completion request returns 403: `Your account role cannot perform this action.`

**Expected:** Require reassignment before removing a role needed to perform assigned open work, or provide an explicit reassignment workflow as part of the change.

**Cause:** `deactivateUser()` calls `abortIfHasOpenWork()`, while `updateUserRole()` only checks the last-Administrator restriction before replacing roles. The reproduced case is a schedule; other work types require equivalent role-aware analysis before choosing the fix.

**Relevant code:** `backend-api/app/Http/Controllers/SuperAdminController.php:567`, `:635`; `backend-api/app/Http/Controllers/FleetController.php:2865`.

**Evidence:** [API cases](../../../logs/superadmin-audit-api.json), [completion denied](../../../logs/superadmin-audit-api-followup.json), [persisted assignment and roles](../../../logs/superadmin-audit-fixture-state.json).

## SA-03 — Activity history and search silently exclude older entries

**Reproduce:** Have more than 200 authorized platform activity entries. Open Activity Log and search for a known older event. The fixture added 205 numbered QA history markers to existing history.

**Actual:** API returns exactly 200 records. `QA history marker 000` is absent while marker 204 is present. Searching for marker 000 reports `No matching activity records` and `0 activity records`, although it exists in the isolated database. The database contained 358 authorized rows at the captured checkpoint. The table paginates only those 200 downloaded records.

**Expected:** Search and pagination cover authorized history, or clearly disclose a recent-history limit and provide a way to retrieve older records.

**Cause:** `activityLog()` applies `limit(200)` with no server-side search or pagination; `ActivityLogTab` searches its downloaded array. The interface labels the scope `Full Platform Log` without disclosing the cutoff.

**Relevant code:** `backend-api/app/Http/Controllers/SuperAdminController.php:688`; `frontend-spa/src/views/SuperAdminPanels.jsx:182`.

**Evidence:** [API truncation](../../../logs/superadmin-audit-api-followup.json), [database count](../../../logs/superadmin-audit-fixture-state.json), [history search screenshot](../../../logs/superadmin-audit-history.png).

## SA-04 — Cannot remove secondary roles while retaining primary

**Reproduce:** In Users, edit an account with primary role Custodian and roles `[Custodian, Maintenance Personnel]`. Choose Custodian as the replacement role.

**Actual:** Save Role remains disabled. The interface says saving replaces current roles, but this valid permissions reduction is impossible directly. The parent handler also returns early when the selected role equals the primary role. Switching through an unrelated role as a workaround temporarily changes access and generates extra audit events.

**Expected:** Allow saving when the resulting complete role set differs, even when primary role is unchanged.

**Relevant code:** `frontend-spa/src/views/SuperAdminPanels.jsx:94`; `frontend-spa/src/views/SuperAdminWorkspace.jsx:632`.

**Evidence:** [browser results](../../../logs/superadmin-audit-browser.json), [disabled Save Role screenshot](../../../logs/superadmin-audit-multirole.png).

## SA-05 — Role filter loses supported legacy accounts

**Reproduce:** Use an approved active account with `role=Custodian` and `roles=[]`, a storage shape supported by `User::allRoles()`. Search for its name in Users, then select Role → Custodian.

**Actual:** The row initially displays Custodian, but disappears after filtering by Custodian. The fixture's backend effective role list is `[Custodian]`.

**Expected:** Filtering, display, and backend permissions agree on effective roles, including the primary role.

**Cause:** Filter uses `account.roles ?? [account.role]`; an empty array does not trigger that fallback. The backend unions the primary role, while row display uses a different fallback. This finding was reproduced using a deliberately seeded legacy storage shape, not an assertion that an existing real account has that shape.

**Relevant code:** `frontend-spa/src/views/SuperAdminPanels.jsx:84`; `backend-api/app/Models/User.php:113`.

**Evidence:** [before/after browser observations](../../../logs/superadmin-audit-browser.json), [effective roles](../../../logs/superadmin-audit-fixture-state.json), [filter screenshot](../../../logs/superadmin-audit-role-filter.png).

## SA-06 — A single failed endpoint disables unrelated pages

**Reproduce:** Return HTTP 500 from `/api/superadmin/concern-reports`, while other endpoints remain healthy. Open Users.

**Actual:** Users shows `Platform data could not be loaded. Retry`; the user search and table are absent even though the users request succeeds. The shared loading operation fetches all datasets as one batch, so this affects other pages using that gate as well.

**Expected:** A concern inbox failure is shown on the relevant feature; healthy account controls remain available. Retry can address the failed dataset.

**Cause:** `loadAll()` uses one `Promise.all()` and assigns none of its datasets if any request fails. The initial error gate replaces the page content. This is a controlled fault-injection result, not an observed spontaneous server outage.

**Relevant code:** `frontend-spa/src/views/SuperAdminWorkspace.jsx:473`.

**Evidence:** [browser result](../../../logs/superadmin-audit-browser.json), [Users blocked by concern failure](../../../logs/superadmin-audit-one-endpoint-failure.png).

## SA-07 — Add Barangay hides geography request failures

**Reproduce:** Return HTTP 500 for `/api/provinces`, then open Add Barangay.

**Actual:** Province has only its placeholder. There is no loading error or retry action; the user cannot complete the form. The UI gives no explanation for the empty selection. Source inspection shows city/barangay failures similarly clear their options without reporting the failure; the province case was reproduced in-browser.

**Expected:** Explain the failed lookup and provide a retry without requiring the user to guess that reopening might help.

**Relevant code:** `frontend-spa/src/views/SuperAdminWorkspace.jsx:188`, `:192`, `:199`.

**Evidence:** [workflow observations](../../../logs/superadmin-audit-workflows.json), [empty form screenshot](../../../logs/superadmin-audit-provision-lookup-failure.png).

## SA-08 — Boundary review guidance conflicts with registration code behavior

**Reproduce:** Use a barangay with a live imported boundary, `boundary_status=needs_review`, no reviewer, and no pending replacement. Open its registration code and regenerate it.

**Actual:** Both API calls return 200; browser displays the code and successfully regenerates it. Add Barangay says to confirm source and geometry before the system issues a code, and the API's blocking message says to review the boundary first. The check actually only rejects missing geometry or a pending replacement, allowing a live unreviewed reference.

**Expected:** The declared onboarding rule and actual behavior agree.

**Classification:** Confirmed **policy/wording discrepancy**, not a demonstrated authorization bypass. An existing `BarangayBoundaryReviewTest` explicitly expects successful code issuance for a live boundary without requiring review. If reference-only onboarding is intended, correct the guidance and errors; if mandatory review is intended, change the checks and tests together. Do not silently impose a new policy based on this audit.

**Relevant code:** `backend-api/app/Http/Controllers/SuperAdminController.php:655`, `:666`; `frontend-spa/src/views/SuperAdminWorkspace.jsx:264`.

**Evidence:** [API checks](../../../logs/superadmin-audit-api.json), [UI regeneration](../../../logs/superadmin-audit-workflows.json).

## SA-09 — Unknown routes render password settings

**Reproduce:** Open `/superadmin/not-a-page` while signed in as Super Admin.

**Actual:** URL remains invalid but the page renders My Settings and the Change password form.

**Expected:** A clear not-found state or a redirect to a valid route. Invalid paths should not silently select an unrelated function.

**Cause:** Unknown tabs fall through to `SettingsTab` and the settings heading/description defaults.

**Relevant code:** `frontend-spa/src/views/SuperAdminWorkspace.jsx:940`.

**Evidence:** [browser route result](../../../logs/superadmin-audit-browser.json), [screenshot](../../../logs/superadmin-audit-unknown-tab.png).

## Controls that passed

- Anonymous platform-user access: 401; tenant Admin attempting platform-user listing: 403; Super Admin fleet access: 403.
- Self-deactivation and last-active-Admin deactivation: 422.
- Deactivation with scheduled work: 422; rejecting an already-approved account: 422.
- Pending QA account activation: 200; concern resolve, reopen, delete: 200.
- Super Admin impersonation target: 403; missing impersonation reason: 422; valid support impersonation: 200.
- Impersonated password write: 403 in testing; logout: 200; reuse of ended impersonation token: 401.
- Missing-boundary code issuance and invalid open-ring boundary upload: 422.
- Invalid GeoJSON surfaced a browser validation error; code regeneration and barangay table pagination worked.
- Wrong current password and mismatched confirmation: 422; mismatch message surfaced in the Settings page.
- Invalid barangay detail ID displayed `Barangay not found` with a return control.

## Evidence and rerun notes

Evidence files use the `logs/superadmin-audit-*` prefix. [Backend test output](../../../logs/superadmin-audit-backend-tests.txt) and JSON scenario results accompany the screenshots.

The [isolated preparation/router](../../../logs/superadmin-audit-harness/prepare.php) use the local workspace path and a scratch folder named `vms-superadmin-20261021` under the Windows temporary directory. Rerun order: create that directory; run preparation; run [additional QA setup](../../../logs/superadmin-audit-setup.php); start the PHP router on 8022 and Vite on 5192 with `VITE_API_BASE_URL=http://127.0.0.1:8022/api`; run API harness; run [history/legacy setup](../../../logs/superadmin-audit-followup.php); run API follow-up and browser/workflow harnesses. Recreate the scratch database before a complete rerun; fixtures and mutations are intentionally stateful. The workflow script's reference ID 12556 is specific to this snapshot. Playwright resolves from the existing local npm cache path. Servers were stopped after the audit.

## Coverage limits

This is a focused Super Admin audit, not an exhaustive proof of correctness. No production deployment, real email delivery, PostgreSQL-specific behavior, live external map accuracy, concurrency/race stress testing, or complete accessibility audit was performed. Impersonation write restrictions were exercised in testing, where the production-style restriction applies; local development intentionally bypasses it. Full valid boundary upload/confirmation combinations and pending registration rejection rely on the existing targeted backend suite rather than a newly executed complete browser lifecycle. The boundary-policy discrepancy needs a policy decision before implementation. Existing unrelated working-tree changes were preserved.
