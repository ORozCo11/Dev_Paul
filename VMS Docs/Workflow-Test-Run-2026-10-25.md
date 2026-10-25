# Ticket and maintenance workflow test run - 25 October 2026

Baseline: revision `37b25ba`, branch `test/ticket-maintenance-workflow-audit`.

## Result and limits

The baseline backend regression suite passed: **545 tests, 2,795 assertions**. After all V2 branches were integrated, the final combined suite passed **612 tests, 5,765 assertions**, including 50- and 100-ticket lifecycle cases. The baseline frontend suite passed **27 tests**; the final updated suite passed **28 tests**. ESLint and the production build passed. No application defect was observed in the completed real API/browser ticket journey. This is regression evidence, not full production acceptance.

The browser journey used a new SQLite database at `tmp/workflow-audit/journey.sqlite`, five generated test users, five test vehicles and a separate API server on port 8002. The frontend was served on port 5173; every API request in these browser contexts was redirected to the disposable server. No production or existing development fleet data was used for these mutations. Test credentials and tokens are confined to the ignored `tmp` directory.

The initial server encountered a sandbox write restriction; restarting the disposable server with approved access resolved it. A first journey helper attempted a vehicle-detail URL that the API does not define; the helper was corrected to use the vehicles list. Neither issue is classified as an application workflow defect.

The expanded live test command was initially blocked by an approval-review usage limit. After approval became available, the external-shop, schedule, maintenance-record and restoration journeys passed against the real disposable API, including a real scheduler conversion. These additional journeys used HTTP API actions; they do not represent a browser pass for every external-shop and schedule form. Deployment, hosted PostgreSQL concurrency, external storage, email delivery and an unattended production scheduler were not tested.

## Completed real API/browser journey

| Scenario | Result |
|---|---|
| Custodian proposes two repair items | Pending Approval; no work starts immediately. |
| Mechanic or Custodian attempts approval | Rejected with 403. |
| Decline without a reason | Rejected with 422. |
| Admin declines through the UI | Declined status persisted. |
| Approve a declined ticket directly | Rejected with 422. |
| Admin undeclines through the UI | Returned to Pending Approval. |
| Admin requests corrections | Approval blocked until Custodian resubmits the same proposal. |
| Admin assigns two mechanics and approves | Active, with separate item assignments. |
| Another mechanic attempts the first item | Rejected with 403. |
| Assigned mechanic submits a real UI repair log and service cost | Submission persisted; second assigned mechanic submitted the other item through the real API. |
| Admin attempts ticket verification | Rejected with 403. |
| Custodian approves without attestation | Rejected with 422. |
| Custodian disapproves one item through the UI | Only the failed item returned to Under Repair; the other stayed submitted. |
| Mechanic resubmits the failed repair | Returned to For Verification. |
| Independent Custodian approves through the UI | Closed; both items Done; exactly two maintenance-ledger records created. |
| Edit repair after closure | Rejected with 422. |
| Vehicle after successful closure | Available. |
| Browser runtime exceptions | None observed. |

Screenshots: `tmp/workflow-audit/ticket-review.png` and `tmp/workflow-audit/ticket-closed.png`. Scripts: `journeys.mjs` and `journeys-resume.mjs` in that directory. The resume script continued the same disposable ticket after correcting the helper URL; it did not create replacement evidence.

## Backend coverage reviewed

A focused subset of **216 tests / 1,330 assertions** is included within the 545-test total, across ticket, schedule, verification, external-shop, donor-part, QA and system workflow suites. These are not additional tests beyond that total.

| Area | Existing passing coverage |
|---|---|
| Proposals and decisions | Role boundaries, linked reports, duplicate prevention, approval, decline, undecline, correction/resubmission and stale revisions. |
| Repair and reassignment | Per-item mechanics, selected unfinished work, preservation of submitted repairs, assignment history and notification recipients. |
| Verification | Required personal-test attestation, independent verification, dual-role self-verification rejection and selective failed-item rework. |
| Cancellation/restoration | Required cancellation reason, notices, retained history/evidence, submitted-work restoration and conflicting source ownership. |
| Deletion/archive | Submitted/paid/evidenced work cannot be permanently deleted; archived records remain recoverable without reopening closed work. |
| External shops | Dispatch/return gates, repeat rework cycles, previous-handler restrictions, cumulative costs and unknown versus zero cost. |
| Schedules | Approval/review rules, ownership, assignment, cancellation/restore, completion, recurrence, conflict warnings and linked-ticket locking. |
| Automatic conversion | Philippine date/service-time handling, Pending Approval proposal creation and prevention of duplicate standalone completion. |
| Maintenance records | Independent verification, confirmation/reopen, duplicate verification protection and prevention of verification bypass. |
| Vehicle state | Other active work and retirement prevent inappropriate release; readiness checks cannot bypass ongoing maintenance. |
| General system | Auth, barangay scoping tests, registration, users, documents, notifications and vehicle validation included in the full suite. |

The existing system audit remains the reference for unresolved findings. A passing regression suite does not close findings that do not yet have complete acceptance coverage.

## Follow-up Condition Monitoring request

The user clarified that **Needs Inspection** should be removed from its table/filter. The verified frontend change removes that display/filter/summary category, retains confirmed Good/Needs Repair outcomes, and uses the recorded outcome while a linked ticket has an inconclusive inspection hold. Legacy inconclusive records retain their stored value and display Not Checked when no confirmed outcome exists. Backend vehicle holds and readiness validation are unchanged. Six focused condition-display tests and the full updated 28-test frontend suite passed. Browser, lint and production-build checks also passed. The separate branch `ui/condition-monitoring-results` was pushed and integrated into `dev-paul`.

## Additional merged-system verification

After integrating the V2 branches into `dev-paul`, the real browser ticket journey was repeated successfully, including stale revision rejection and premature readiness rejection. The following additional journeys passed through the real API:

- External dispatch and return gates, prohibited mechanic dispatch, cancellation rejection while at the shop, two repair cycles, and one ledger record with cumulative invoice cost of 1,500.
- Schedule proposal, decline with a reason, undecline, approval, assigned-mechanic-only completion, duplicate-completion rejection and creation of the next recurring occurrence.
- Maintenance-record failed verification, authorized Admin repair updates, personal-test attestation, duplicate-verdict rejection, Admin reopening and final confirmation. A mechanic's record-edit attempt was correctly rejected with 403; the journey was continued with the documented Admin role.
- Ticket cancellation reason, restoration to active work, rejection of deletion of active work, and restoration of a cancelled schedule to Pending Approval.
- Actual `schedules:convert-due-to-tickets` execution against the isolated database, idempotent second execution, prevention of parallel standalone completion, approval/repair/verification of the generated ticket, and preservation of Scheduled Maintenance source and a genuine zero cost.
- Generation of notifications and completed-ticket archives.

Evidence: `tmp/workflow-audit/extra-results.json`, `journey-results.json`, and the corresponding scripts. Super Admin overview and barangay management also rendered without runtime exceptions after integration fixes. See [the integration record](Branch-Integration-2026-10-25.md) for final combined regression results.

## Reproduction

```powershell
C:\webdev\php\php.exe backend-api/vendor/phpunit/phpunit/phpunit -c backend-api/phpunit.xml --no-progress --do-not-cache-result --log-junit tmp/workflow-audit/backend-baseline.xml
cd frontend-spa
node --test tests/*.test.js src/utils/ticketTimeline.test.js
npm.cmd run lint
```

The backend configuration selects SQLite `:memory:`. Do not point the live browser journey at a production database or the existing development database. The disposable seed refuses to overwrite an existing journey database.
