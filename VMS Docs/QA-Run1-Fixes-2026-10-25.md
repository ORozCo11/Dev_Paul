# QA Run 1 findings: implementation and retest

Date: 25 October 2026 (Asia/Manila). Baseline: 2ccd7cf. This follow-up uses the user's 37-item summary plus direct source inspection. The original /mnt/user-data/outputs/VMS-QA-Audit-Report-Run1.md was not accessible in this Windows workspace. Browser checks use mocked synthetic API responses; PHP tests exercise actual controllers against isolated SQLite. These are different forms of evidence, not a production acceptance certificate.

## Disposition of all 37 findings

Fixed means the described implementation has changed; the validation section distinguishes automated coverage from source inspection. Reconciled means the claim required qualification or current behavior is deliberate. Historical data and historic missing logs are not invented/backfilled.

| Finding | Disposition and resulting behavior |
|---|---|
| QA-001 | Fixed: empty issue results describe filters, rather than falsely declaring no fleet problems. |
| QA-002 | Fixed: schedule completion and manual maintenance completion dates cannot exceed today's Philippine date. Rejected requests create no receipt/recurrence. |
| QA-003 | Fixed: only the schedule's creator can edit; other Custodians receive 403 and no edit icon. Actual edits return to Admin review. |
| QA-004 | Fixed: issued impersonation tokens retain initiator/reason; every subsequently created Activity Log row captures initiator, token session ID and reason, with an impersonation marker in its detail. The picker records a generated account-and-location access description; the manual reason input was removed at the user's request. This description is not a written justification supplied by the operator. Old logs/tokens cannot acquire an invented initiator. |
| QA-005 | Fixed: repair closure invalidates pre-repair readiness evidence. Availability and physical condition still reflect independently verified work and other outstanding work; Ready requires a subsequent readiness attestation. No redundant condition log is fabricated. |
| QA-006 | Fixed: proposals, decision changes, work events, handoffs and closure add typed Vehicle History records. Closure explicitly links the ticket; generic fleet recomputation is called Operational Status Updated and links the vehicle. Missing historical events are not fabricated. |
| QA-007 | Fixed: table overdue checks match the dashboard's standalone-schedule scope and Philippine day; converted schedules are managed through their tickets. |
| QA-008 | Fixed/reconciled: pending proposals reserve their issue as Under Review, not In Maintenance. A new reported problem flags Needs Inspection; approval, not mere proposal creation, starts repair. A second mandatory inspection is not inserted into the established Custodian-proposal/Admin-review flow. |
| QA-009 | Fixed: operational staff can add catalogue suggestions/values, while edit/delete are Admin-only in server permissions and the UI. Broader global catalogue tenancy/historical-rename concerns in the older audit are separate and remain outside this narrow role correction. |
| QA-010 | Tightened: Active and For Verification tickets cannot be deleted, even without repair logs. Cancel with a reason preserves approval history. Existing repair-evidence protection and Closed permanence remain enforced. |
| QA-011 | Fixed: unauthorized/missing ticket detail has an always-visible explanation and Back to tickets action, independent of dismissible hints. |
| QA-012 | Fixed: offset-free legacy repair timestamps are interpreted as API UTC; date/time presentation explicitly uses Asia/Manila. |
| QA-013 | Fixed: new proposal titles use the first actual problem title, not generic Repair Type such as Other. |
| QA-014 | Fixed: Completed and Awaiting Verification schedule stage totals are disjoint; list filters use the same stage function, including mixed selections. Assigned-to-you remains an explicitly overlapping ownership subset. |
| QA-015 | Fixed: ledger tiles explicitly count Total Repair Records and record-stage counts. Vehicle list rows remain grouped, with explanatory text; the heading counts displayed vehicle groups. |
| QA-016 | Fixed: validation summaries remain in document flow instead of blocking the form; manual form summaries expire after eight seconds. Main notices already had timers; navigation now clears stale feedback. Inline field errors remain until corrected. |
| QA-017 | Improved: work-item rework reason is visible without expanding history, and work-order cards show failed items/reasons. The existing repair form rejection banner remains. |
| QA-018 | Fixed: schedule approval opens a confirmation naming vehicle, maintenance and date before the request. |
| QA-019 | Fixed: module selections use role-root URLs with a module query parameter. Navigation, tab selection, browser Back and reload retain the selected module; returning from forms clears dirty state. |
| QA-020 | Clarified/improved: normal direct barangay-role logins have no switcher controls. At the user's subsequent request, the dedicated impersonation@test.com switcher retains its picker inside every supported role workspace, allowing repeated account switches. Other support sessions retain the return-to-original-account control. Dedicated switcher and Super Admin support can exist in deployment; non-local impersonated writes remain blocked and deployed tokens expire after 30 minutes. This does not claim to inspect the deployed configuration. |
| QA-021 | Fixed: history/exports label related records by type (Ticket #17 vs Issue #17 vs Vehicle #17); generic report IDs are also typed. |
| QA-022 | Fixed: schedule times consistently use HH:mm; unknown costs stay distinct from zero. Reports format known amounts as PHP and preserve missing values as missing. |
| QA-023 | Fixed: failed standalone verification requires notes; passing requires explicit personal-test attestation, saved with the record. Changed/reopened work clears attestation alongside the prior verdict. Independent repairer restriction remains. |
| QA-024 | Fixed/reconciled: readiness confirmation is blocked while the vehicle is Under Maintenance or has an active repair ticket; the vehicle row is locked during validation/save. The current simplified personal-readiness attestation remains; a photo is not represented as proof of a physical test. |
| QA-025 | Fixed: both fetched and initially supplied type options respect selected domain, including Enter-key selection. Changing domain clears the old type; server rejects an explicitly mismatched submitted domain. |
| QA-026 | Fixed: shared add/edit/import capacity rules require a positive quantity, rejecting negative, zero and non-quantity text. |
| QA-027 | Clarified: existing plate rules already support the listed seed examples. Helper text now explicitly includes INEM-112, FDR-303 and AMB-1101 rather than implying one exclusive plate series. |
| QA-028 | Fixed: live wizard values, including quantity changes, are retained before Back/step navigation; current-step fields do not reset just because the catalogue updates. |
| QA-029 | Fixed: an unchanged correction payload is refused before decision history/revision changes. Resubmitted titles use the corrected problem title. |
| QA-030 | Fixed/reconciled: a logged schedule awaiting independent verification holds availability and marks Needs Inspection, not a newly diagnosed Needs Repair. State recomputation preserves a newer physical Good observation when no active repair exists; genuine active repairs continue to hold Needs Repair. |
| QA-031 | Reconciled: speed and absence of a file alone do not prove a bypass. Admin handling approval/shop sending/return is intentional. Existing guards require return before submission and prohibit current/prior shop handlers from verifying. Independent Custodian approval is still mandatory; no minimum elapsed time or mandatory photo is invented. Existing external workflow tests cover these boundaries. |
| QA-032 | Fixed: Admin work-order rows use all mechanics, while mechanic rows stay assignment-scoped. Counts use actionable/finished work-order stages, excluding proposals/cancelled tickets. Card headings use displayed groups. |
| QA-033 | Fixed with QA-012: timeline and repair entries share explicit UTC parsing and Manila display. Storage remains UTC. |
| QA-034 | Fixed: return/cancel navigation explicitly clears the dirty flag, including Cancel on Add User. |
| QA-035 | Fixed for display/validation: removed the arbitrary eight-field report limit that hid cost; rows expose the union of available fields, readable typed IDs, currency and Manila timestamps. New invalid values are blocked, and reports explicitly flag legacy invalid capacities/future completed-work dates. Pre-existing bad dates/capacities are preserved for evidence and require factual correction, not guessing; these are a data-remediation follow-up. |
| QA-036 | Duplicate manifestation of QA-007; covered by the same overdue predicate. |
| QA-037 | Improved: Admin has an explicit Declined schedules shortcut with its count, in addition to status controls. Ongoing defaults remain deliberate. |

## Migration and rollout

New reversible migration: backend-api/database/migrations/2026_10_25_000001_add_qa_audit_safeguards.php.

- Adds vehicle readiness invalidation timestamp; initializes existing vehicles at migration time so earlier readiness passes require renewal.
- Adds initiator/reason metadata to impersonation tokens and initiator/session/reason metadata to activity logs.
- Adds the standalone-record test_attested flag. It does not assert that old records were physically tested.
- Down removes only these new fields. Keep a backup: rolling down necessarily discards their new metadata.

After pulling, run php artisan migrate from backend-api and rebuild/restart the frontend. Sign out/in to refresh changed catalogue abilities. Existing role/tenant/server enforcement remains authoritative even before the browser refreshes its controls. Previously created impersonation sessions have no newly invented initiator metadata; end them and start a new reasoned session.

Local application: a consistent SQLite backup was saved at tmp/qa-run1-fixes/before-qa-run1.sqlite before migrating. Six vehicles before, six after; no reseed/reset/deletion. Read-only anomaly inspection found vehicle #16 with capacity -5 kg and maintenance record #12 with work date 2027-10-24. After the user authorized a standard/demo capacity correction, vehicle #16 (QA-Brand / QA-Model) was updated to 8 pax, matching existing emergency-vehicle sample capacities. Remarks explicitly identify a demo value, not a manufacturer rating. Vehicle History and Activity Log preserve the -5 kg -> 8 pax correction without falsely attributing it to an Admin. All six vehicles remain. Record #12 still needs its actual work date; it remains unchanged and flagged in reports. A populated-database test exercises migration down/up and preserves its vehicle. Fresh in-memory test databases exercise up during the regression suite.

## Validation

Final verification: **537 backend tests / 2,718 assertions passed; 20 frontend tests passed; frontend lint and production build passed.** Six targeted UI workflow checks, seven ticket-decision browser checks and three maintenance-report browser checks passed (16 total), with no page runtime errors. The mobile wizard and desktop report screenshots were visually inspected. Browser harnesses/evidence are local under tmp/qa-run1-fixes/ and tmp/ticket-decision-fix/; they are not deployed application code. Permanent backend acceptance cases are in QaRunOneTest.php; frontend date/stage/related-ID cases are in qaWorkflow.test.js. Existing tests were updated only where the accepted ownership, catalogue, title, attestation or deletion rules changed. The leap-year recurrence test now runs at its simulated 2028 completion date instead of submitting a future completion from today's clock.

Browser checks exercise ticket correction/decline/cancel/restore, module URL/back/reload, declined-schedule access, wizard data/domain filtering, user-form cancellation and direct-ticket denial. Mock APIs validate UI behavior, not database concurrency or the physical reality of a repair. Expected 403/409 resources are intentional negative cases; page runtime errors are checked separately.

No PostgreSQL concurrency/load test, deployed-server inspection, or human physical vehicle inspection is claimed. The six older broad system-audit findings are not all closed by this narrower Run 1 fix set. The earlier Claude browser-skill package remains separate work in the working tree.
