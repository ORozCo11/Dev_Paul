# Ticket process review — 24 October 2026

Reviewed application revision: `9314091`. The original findings below describe that revision. The implementation follow-up at the end records the subsequent fixes and verification; this is not a claim of production acceptance.

## Overall assessment

The central process is useful: Custodian proposal → Admin review and work assignment → repair → independent Custodian verification → automatic closure, maintenance history and linked-schedule completion. This preserves accountability while automating the clerical handoffs.

The implementation is not yet consistent enough to call the ticket process complete. External-shop records and rework need the earliest fixes. Several intentional whole-ticket rules also cause unnecessary repeated work now that one ticket can have different mechanics per sub-issue.

## Verification performed

- Existing targeted regression suite: **107 tests / 631 assertions passed**. Classes: `TicketWorkflowTest`, `TicketProposalWorkflowTest`, `ExternalShopTicketTest`, `ConvertDueSchedulesToTicketsTest`, `ScheduleCompletionTest`.
- Eight additional investigation probes: **8 tests / 59 assertions passed**. They deliberately assert the observed gaps, so this result reproduces problems rather than proving acceptance.
- Laravel tests use SQLite `:memory:` and `RefreshDatabase`. No live vehicle records or accounts were changed.
- Frontend findings below are from code inspection. No new browser usability session, timed task study or production scheduler test was performed. No frontend source changed, so build/lint were not rerun for this review.
- Local reproduction source: `tmp/ticket-review/TicketProcessProbeTest.php` (ignored investigation scratch; not a permanent acceptance suite). Run from `backend-api`: `php vendor/phpunit/phpunit/phpunit ../tmp/ticket-review/TicketProcessProbeTest.php --no-progress`.
- Existing regressions: `php artisan test --compact --filter="TicketWorkflowTest|TicketProposalWorkflowTest|ExternalShopTicketTest|ConvertDueSchedulesToTicketsTest|ScheduleCompletionTest"`.

## Confirmed defects and control gaps

### TPR-01 — External repairs disappear from the unified maintenance ledger

**High priority. Reproduced.** The proper external path was exercised: approve, send to shop, receive, log repair, Custodian approve. The ticket closed, but **zero maintenance records** were created. `finalizeConfirmedSubIssue` only creates a ledger entry when `assigned_mechanic_id` exists; external tickets intentionally have no in-house mechanic.

The probe also recorded PHP 12,000 as `external_actual_cost`, while `maintenance_cost` remained effectively zero. Archive cost is summed from `maintenance_cost`, so the two cost fields can disagree. A closed external repair therefore cannot be treated as reliably represented in unified maintenance history and spending.

Source: `backend-api/app/Http/Controllers/TicketController.php:3088`, `:3104`, `:2007`, `:3208`.

**Required correction:** create one ledger entry for each approved work item regardless of method. Record external provider and responsible internal handler distinctly from an in-house performer. Use the accepted external invoice/actual cost consistently in ledger, ticket, archive and report totals. Preserve unknown cost as unknown; do not silently imply a free repair. Test that closure creates exactly one entry and one financial contribution.

### TPR-02 — External completion can skip sending and receiving

**High priority. Reproduced.** After approving an external proposal, Admin could log it as completed without any send or return record. It moved to For Verification and the Custodian could close it. The guard only blocks a job that has a send timestamp and no return timestamp; it does not require either timestamp for an external job.

Source: `backend-api/app/Http/Controllers/TicketController.php:2062-2069`.

**Required correction:** server-enforce approved → sent → returned → ready for verification for normal external work. If historical work needs retrospective entry, make that an explicit, audited path rather than an implicit bypass. The interface should enable the next valid action and explain the current blocker.

### TPR-03 — Mechanic can change the approved repair method without review

**High priority. Reproduced through the API; the current form does not expose this switch.** An assigned mechanic changed an approved in-house job to `external` in the repair-log payload, named a shop and moved the ticket to verification without Admin handling or shop send/return records.

Source: `backend-api/app/Http/Controllers/TicketController.php:2035-2052`, `:2062`, `:2133`.

**Required correction:** enforce repair-method authority on the server. Allow mechanics to request a method change with a reason; require Admin review when it changes the approved provider, responsibility or external spending. Preserve the original decision and change history. A simple note correction should not trigger an entire approval cycle.

### TPR-04 — External rework has no working repeat shop cycle or responsible notification

**High priority. Reproduced.** Rejecting a properly returned external repair made the ticket Active again, but generated no new notification to its Admin handler. Rework notifications go only to mechanic IDs, which are absent for a normal external ticket. Sending it to the shop again was rejected because `external_sent_at` was already set. The old send/return fields cannot describe a second trip to the shop.

Source: `backend-api/app/Http/Controllers/TicketController.php:1273-1313`, `:1256`, `:1964`, `:2005`.

**Required correction:** notify the responsible Admin handler and provide an explicit rework cycle with its own send/return dates, notes and warranty/cost treatment. Preserve the first cycle. The next action should say who must act and what failed.

### TPR-05 — Hidden repair-date inputs can create impossible completed history

**Medium priority. Reproduced through the API.** A repair start of 1 February 2040 and completion of 1 January 2040 were accepted, then copied into the completed maintenance record. Removing the date inputs from the form did not remove or validate those API inputs.

Source: `backend-api/app/Http/Controllers/TicketController.php:2043-2044`, `:2130-2131`, `:3110-3111`.

**Required correction:** automatically record repair submission time, verification time and closure time as distinct events. Reject unsupported manual date overrides, or validate authorized historical overrides against order and the agreed Philippine calendar rule. A repair completed on Monday but verified on Wednesday should retain both dates without making users retype them.

## Confirmed behavior requiring a workflow decision

### TPR-06 — Reassignment moves and resets every work item

**Medium priority; usability/design gap. Reproduced.** Approval supports different mechanics per sub-issue. The available reassignment remains whole-ticket: both work orders were reassigned to one mechanic, including a submitted repair, and both became Under Repair. The dialog does say it moves the whole ticket, so this is not an undisclosed API behavior; it is an incomplete fit for the newer per-item assignment model.

Source: `backend-api/app/Http/Controllers/TicketController.php:904-958`; `frontend-spa/src/views/Workspace.jsx:11811-11823`; `backend-api/config/permissions.php:171-172` (old per-item endpoints are not granted).

**Recommended rule:** reassign the selected unfinished work item by default. Preserve completed submission evidence and the original performer. Offer an explicit bulk transfer only when wanted. Continue excluding every actual repairer from independent verification.

### TPR-07 — One failed item forces every mechanic to resubmit

**Medium priority; intentional behavior with avoidable repeated work. Reproduced.** Two mechanics submitted separate jobs. Rejecting only the brake repair, while explicitly saying the siren passed, returned both jobs to Under Repair.

Source: `backend-api/app/Http/Controllers/TicketController.php:1280-1290`.

**Recommended rule:** Custodian identifies the failed work items and supplies a reason for each. Only those items return for rework; passed items retain their evidence. Keep the ticket open until all items pass and retain a final vehicle-level fitness decision, because a collection of individual repairs is not by itself proof that the vehicle works safely as a whole.

### TPR-08 — Approval records attestation without explicit test evidence

**Medium priority; evidence/design discrepancy, not a bypass of the current UI contract. Reproduced.** The current dialog intentionally offers Approve/Disapprove without a checklist. Even an empty API payload defaults to approval; the resulting sub-issue stores `test_attested=true` with `functional_test=null`. Selecting Approve may be intended as sign-off, but stored operator-test evidence is stronger than what this interaction explicitly captures. Comments still describe a required checklist that is no longer present.

Source: `backend-api/app/Http/Controllers/TicketController.php:1147-1194`; `frontend-spa/src/views/Workspace.jsx:11068-11123`.

**Recommended rule:** require an explicit verdict and make its meaning clear: “I tested this vehicle and confirm these repairs passed.” Use a short relevant check/result per work item where evidence is needed; do not force long generic checklists for every repair. Never manufacture an attestation or test result from an omitted field. Adviser/user validation should settle the exact evidence requirement.

## Field review

| Stage / field | Assessment | Recommended behavior |
|---|---|---|
| Vehicle | Required and appropriate; should identify an existing fleet record. | Search by name and plate; show photo, condition, availability and readiness separately. Typing should search, not create a vehicle from this picker. |
| Repair method | In-house / external is understandable. | Label it “Repair method”; keep “Repair type” for the category such as brake repair. Validate method changes as TPR-03 describes. |
| General Details + sub-issue title | Both are collected for in-house work; users may repeat themselves. | Use overall observations only for context; each work item states one problem. Prefill from a linked issue/condition check and let users correct the actual scope. Validate whether general Details needs to remain mandatory. |
| Repair type / maintenance type | Optional category is reasonable; free-text problem details must remain useful without it. | Consistent “Repair type” label. Provide controlled existing values and an “Other” detail path for roles that cannot maintain catalogs. Never make a user invent a category just to submit. |
| Priority | Useful, currently chosen by the proposer and reviewable by Admin. | Add short criteria/examples and a suggested level; keep human override. Distinguish urgency from whether the vehicle is safe to use. |
| Suggested mechanic | Helpful when the Custodian knows who is available, but not required. | Clearly optional; Admin confirms each assignment. Prefill suggestions without treating them as actual dispatch or approved work. |
| Assigned mechanic per work item | Correct for jobs needing different specialists. | Active, same-barangay mechanic; show workload. Support item-specific reassignment and prevent selecting a verifier who cannot be independent. |
| External reason + Details | Both are useful: why outsource, and what is required. | Keep reason structured and Details plain language; do not ask for the same scope twice. |
| Shop/contact/estimated cost | Useful but often unknown at proposal time. | Optional at proposal, required only at the stage where needed; carry forward reviewed values. |
| Sent By | Ambiguous: transport person vs accountable Admin. | Store the accountable handler from login; record a different transporter only in a separate optional field. Do not overwrite the transporter's identity with the handler's name. |
| Work performed | Necessary evidence of action; current repair notes append on resubmission. | Keep concise, timestamped and attributable; show prior notes without asking users to retype them. |
| Parts and cost | Optional is appropriate for jobs using no replacement parts. | Separate parts, labor/service and shop total. Current form totals only part/material rows; it has no named labor/service amount (`Workspace.jsx:15258-15265`, `:15499-15527`). Distinguish no charge from cost not yet recorded. |
| Attachments | Useful supporting evidence. | Keep optional unless a specific policy requires a document. Attachment alone must not replace independent verification. |
| Repair dates | Repeated manual entry is unnecessary. | Automatically timestamp actual events; retain guarded historical corrections only if needed. See TPR-05. |
| Verification result/reason | Independent sign-off is essential. | Explicit verdict, selected failed items, actionable reasons and truthful test evidence. No Admin fallback certification. |

Frontend source: proposal `Workspace.jsx:12781-13210`; Admin review `:11270-11395`; repair form `:15231-15540`.

## Automation and usability gaps found in source review

1. **External information is retyped.** The repair send form starts its reason, scope, contact and estimated cost blank, although proposal fields already hold those values. Prefill them from the reviewed work item (`Workspace.jsx:15248`). Confirm changes rather than forcing repeat entry.
2. **Read-only helper failure looks like an empty queue.** Open-report lookup errors become `[]`, so users cannot distinguish “no report” from “lookup unavailable” (`Workspace.jsx:12942-12952`). Show a retry message without discarding the draft.
3. **Submission success could lead to the created record.** `proposeTicket` discards the created response and returns a boolean, so the new ticket ID is not available to the form's success flow (`Workspace.jsx:1282`). A direct detail view with the next responsible person is easier to follow than requiring a new search.
4. **Scheduled maintenance needs a visible timing contract.** Conversion is daily, checks only the date, and the application configuration uses UTC (`routes/console.php:17`, `ConvertDueSchedulesToTickets.php:52-55`, `config/app.php:68`). With this schedule and timezone, the daily job targets 08:00 Philippine time, not the selected service time. Verify actual scheduler deployment, choose the intended Philippine timing, and show what happens before conversion. This review did not test the deployed scheduler.
5. **Duplicate prevention needs an actionable escape.** A duplicate proposal returns “Add this as a sub-issue,” but Custodians cannot manage active sub-issues and the active ticket UI does not offer that flow (`TicketController.php:627-630`, `:1756`; permissions `subissue.manage`). Give a link to the existing ticket and a permitted way to notify Admin or request a scope change. Do not tell the user to perform an action their role cannot access.
6. **Check verifier availability before approving.** Current reassignment warns about a present mechanic conflict but permits it, and the final verification rejects current and previous repairers. Filter/warn using the same full conflict rule before assignment, and explain when a separate authorized Custodian is required. Impersonation must not substitute for a real independent person.

## Proposed target workflow

1. **Custodian reports or proposes once.** Search vehicle, reuse a linked report/check, enter distinct problems, choose method and priority. Save a recoverable draft; show matching open work before submission. Reporting a concern must remain possible without the Custodian pretending to know the technical repair solution.
2. **Admin reviews once.** Confirm scope, urgency, independent Custodian and one active mechanic per in-house item. For external work, confirm scope and responsible Admin handler. Request corrections explicitly when needed rather than requiring duplicate proposals.
3. **Mechanic records actual work.** Open assigned item, record work/parts/evidence and submit. Server records actor/time. Donor use records its impact and requires fresh readiness evidence where relevant; retain separate availability, physical condition and verified readiness.
4. **External handler records a real shop cycle.** Reviewed details are prefilled; send, receive with actual invoice/result, then submit for verification. A rejection creates a repeat cycle and a handler task.
5. **System performs the handoff.** When all required work is submitted, notify the assigned Custodian once. No extra mechanic submit step should be necessary in the normal path.
6. **Custodian verifies real outcomes.** Approve passed items, return failed items with reasons, and confirm overall vehicle fitness. Never self-verify. Preserve earlier submissions and rework cycles.
7. **System performs closure bookkeeping.** Close only after the required verification; write exactly one ledger entry per item for every method, resolve linked reports, complete linked schedule/recurrence, archive evidence, notify participants and recompute fleet status considering other open work. Do not equate ticket closure with a new readiness inspection.

## Implementation order and acceptance

1. Fix TPR-01–04 together: correct external ledger/cost, enforced method stages/authority, and rework cycles/notifications. Test both direct APIs and normal UI.
2. Fix date integrity and explicit verification evidence (TPR-05/08). Preserve automated timestamps; do not add unnecessary manual fields.
3. Make reassignment and rework fit per-item mechanics (TPR-06/07), keeping an independent final vehicle decision.
4. Remove repeated typing and dead ends in the six usability items above. Preserve drafts across recoverable failures.
5. Convert reproduced defects into permanent tests that assert rejection/correct bookkeeping, then rerun all backend tests, frontend lint/build and browser workflows at desktop/mobile widths. Include concurrent completion/reassignment/verification and retry scenarios.
6. Ask representative Admin, Custodian and Maintenance Personnel users to perform: proposal from issue, two-mechanic repair, one-item rework, external warranty rework, absent mechanic handover and scheduled service. Measure completion rate, time, duplicate entry, wrong turns and assistance needed. Record the baseline first; no speed or effectiveness improvement has been measured in this review.

The existing [system audit](System-Audit-2026-10-24.md) still applies, especially the file-signing and issue-detail authorization gaps, donor-readiness invalidation, catalog historical integrity, CSV safety and future schedule dates. This ticket-focused review does not close those findings or replace target-host acceptance.

## Implementation follow-up — 24 October 2026

The eight findings above have been addressed in code and permanent acceptance tests:

| Finding | Implemented behavior |
|---|---|
| TPR-01 | Every verified external item creates a unique linked maintenance record with the external provider. Known invoice costs accumulate across visits; unknown remains null and zero remains zero. Closure retries cannot create duplicate ledger records. Reliability spending excludes Done items already represented in the ledger. |
| TPR-02 | External work requires recorded sending and return before repair submission. The form blocks premature submission and explains the next action. |
| TPR-03 | API calls cannot change to or from external repair in a repair log. Such a change requires a new Admin-reviewed proposal. A repair log cannot overwrite the shop, warranty or invoice total recorded through shop actions. |
| TPR-04 | The responsible external Admin receives rework notifications. A rejected repair can start a new shop visit, preserving its previous cycle and work events. Previous external handlers remain excluded from verification. |
| TPR-05 | Repair submission automatically records its completion date and an actor/time event. Unknown actual start dates remain unknown. Future or reversed historical dates are rejected. Verification and closure retain distinct timestamps. |
| TPR-06 | The interface selects one unfinished work item for reassignment. Submitted repairs cannot be reassigned until returned for rework. Other mechanics' assignments and submitted evidence stay unchanged. The existing bulk API moves only unfinished in-house items. |
| TPR-07 | The dialog selects failed items and supplies a reason; other submitted items stay submitted. After failed work is resubmitted, the ticket automatically returns to the assigned Custodian for the final vehicle-level decision. Legacy rejection payloads without item IDs still return all submitted work. |
| TPR-08 | The API requires an explicit verdict and a true personal-test attestation for approval. The dialog captures that confirmation and never invents checklist results. |

Usability changes: reviewed external details prefill the shop form; duplicated shop/warranty inputs were removed; in-house labor/service cost can be recorded separately from parts in the form's total; proposal success opens the created ticket; failed issue lookups show a recoverable warning; duplicate-ticket errors direct the Custodian to ask Admin for scope review. Reassignment warnings include prior repairers and handlers, and the approval form catches selecting the verifying Custodian as mechanic. Work/verification events and earlier shop visits are visible in expanded ticket items. Duplicate form submissions are disabled while saving.

Schedule conversion checks every minute against the Philippine calendar and selected service time, with overlap protection. The host must still invoke Laravel's scheduler each minute; this change does not provision or certify a production scheduler.

### Migration and rollout

- New migration: `backend-api/database/migrations/2026_10_24_000001_preserve_ticket_work_evidence.php`.
- Adds `external_cycles` and `work_history` JSON fields to ticket work items, plus a unique nullable `ticket_sub_issue_id` link on maintenance records. Existing statuses, roles and vehicle records are retained.
- Team/deployment rollout: run `php artisan migrate` from `backend-api`, install locked dependencies as usual and rebuild the frontend. No reseed or database reset is required.
- Applied to the local SQLite database after a consistent backup in ignored `tmp/ticket-review/before-ticket-process.sqlite`; vehicle count remained **5 before / 5 after**. There were **0 historical closed external items** locally needing ledger recovery.
- The migration does not fabricate historical work events or backfill already-closed repairs in other databases. Such records need an evidence-based reconciliation. Rolling back removes the newly added evidence/link columns; retain a database backup if rollback is needed.

### Final verification

- **502 backend tests / 2,461 assertions passed**, including 13 permanent ticket-integrity tests in `backend-api/tests/Feature/TicketProcessIntegrityTest.php`.
- Migration upgrade/rollback was exercised with existing vehicle and maintenance records in isolated SQLite. Fresh test databases run the new migration as part of the suite.
- Frontend lint and production build passed. PHP syntax checks passed for all 17 changed/new PHP files.
- Eight route-mocked browser scenarios passed without runtime errors: approval attestation, selected-item rework, individual reassignment, mobile repair/service-cost layout, prefilled shop details, blocked premature completion, external mobile layout, and repeat external rework. Screenshots were inspected. These checks isolate UI behavior; live API transitions are covered by the backend tests.
- A full-suite run exposed a pre-existing random plate collision in `ScheduleConflictWarningTest`; its fixture now uses deterministic unique values. The complete suite passed after that correction.

No changes to dispatch, impersonation, registration, hubs or unrelated role grants were made. The six broader system-audit findings remain open. Representative-user timing studies and target-host acceptance remain necessary before claiming measured speed improvements or production readiness.

## Decision-path recheck — 24 October 2026

Reviewed revision: `4e5e222`. This additional review focused on proposal decisions and cancellation/deletion/restoration. The findings below describe that revision and the original recheck; the subsequent decision-path implementation follow-up records their resolution.

### Current rules confirmed

- Admin approves only Pending Approval proposals. In-house work requires an active mechanic for every item; approved external work has no in-house mechanic.
- Decline requires a reason and produces Declined. It does not delete the proposal or mean the physical defect has been fixed.
- Admin must undecline before approving a declined proposal. Undecline returns it to Pending Approval and notifies the Custodian; it does not activate work.
- Proposal approval and repair approval are different: only the assigned independent Custodian can approve completed repair work and close the ticket.
- Closed tickets are rejected by ordinary cancellation/deletion requests. The stale-state findings below concern missing rechecks after another decision changes the record.

### TDR-01 — Decision actions can overwrite a newer state

**High priority. Reproduced using stale controller-model snapshots; real parallel database requests were not exercised.** Approval rereads and locks the ticket before changing status. Decline, undecline, cancel, uncancel and delete do not consistently do so. Their initial checks rely on an earlier model snapshot.

Probes demonstrated: a stale pending snapshot declined an already-approved ticket while its item remained Under Repair; a stale ticket/work snapshot cancelled a Closed ticket while retaining its closure date; and a stale active snapshot deleted a Closed ticket while its ledger record remained. The cancellation probe includes previously read work-item relations, modelling a competing decision after the eligibility check. These are authorized Admin decision races, not unauthenticated access.

Source: `TicketController.php:814-817`, `:1291-1347`, `:2846-2949`.

**Required rule:** lock and freshly validate the ticket and affected linked records inside every decision transaction. Once another action closes or changes it, reject the stale decision and refresh the UI. Keep status, evidence, logs and notifications in the same transaction.

### TDR-02 — Deleting submitted paid work loses repair evidence

**High priority. Reproduced through the API.** A mechanic submitted real repair notes and PHP 500 cost. Admin then deleted the ticket. No archive or maintenance record remained, and the work item was deleted. The archive condition checks only `status === Done`; modern submissions are For Inspection until the entire ticket closes.

Source: `TicketController.php:2927-2948`; `Workspace.jsx:12219-12220` exposes Delete for submitted, non-Closed work.

**Required rule:** restrict permanent deletion to untouched mistakes. Once work, attachments, donor use, shop events or cost exist, retain a full immutable archive or refuse deletion and require audited cancellation. Submitted work is evidence even before Custodian verification.

### TDR-03 — Restoring an external ticket can strand completed submissions

**High priority. Reproduced through the API.** Cancel an external ticket already For Verification, then restore it: the ticket becomes Active but its item stays For Inspection. Admin cannot call the mechanic-only submission fallback, repair logging rejects the already-submitted item, and Custodian verification rejects the Active ticket. There is no normal next action to complete it.

Source: `TicketController.php:2890-2911`, `:954` (submission fallback), `:1096` (verification state check).

**Required rule:** restoration must derive the correct state from preserved work. If every repair is already submitted, restore directly to For Verification and notify the assigned Custodian once. Otherwise restore Active with only unfinished items available to their responsible actor. Do not require retyping completed work.

### TDR-04 — Undecline can reclaim an issue already assigned elsewhere

**High priority. Reproduced through the API.** Decline proposal A, reuse its now-Pending report in a corrected proposal B, approve B, then undecline and approve A. Both became Active for the same report. Closing B marked that report Resolved while A remained Active. Undecline does not recheck whether another ticket claimed the report, and report resets blindly update its status.

Source: `TicketController.php:574-577`, `:1335-1347`, `:3182-3191`.

**Required rule:** one current ownership rule for linked reports/checks, enforced under lock during propose, approve, undecline and restore. Reject an old proposal's return if another live ticket owns its source; show the owning ticket and permitted next action. Do not let old-ticket actions reset another ticket's report.

### TDR-05 — Decline has no Custodian correction/resubmission path

**Medium priority; confirmed usability gap.** Declining with “correct the details” leaves the Custodian unable to edit/resubmit that proposal. A new proposal for the same first issue was rejected as a duplicate because Declined still participates in the open-ticket duplicate check. Admin can undecline and edit, but the original reporter has no direct correction action.

Source: `TicketController.php:622-630`; `Workspace.jsx:11215-11239`, `:11445-11458`; `permissions.php:154-170`.

**Recommended rule:** distinguish “Request corrections” from final decline. Let the assigned Custodian revise the same unapproved proposal and submit it for fresh Admin review, preserving review history. A final decline should clearly explain whether the concern remains open and who must act. Undecline remains an Admin reconsideration action.

### TDR-06 — Cancellation lacks a required reason and targeted notice

**Medium priority; confirmed by source inspection, not a separate API probe.** The UI sends an empty cancellation payload; the backend permits missing notes and does not notify assigned mechanics/Custodian. Its activity entry records only that cancellation occurred. Restoring clears `closing_notes`, so even a supplied reason is not preserved in that ticket field.

Source: `Workspace.jsx:11661-11670`, `:12306`; `TicketController.php:2863-2880`, `:2898-2911`.

**Recommended rule:** require a short reason, preserve cancellation/restoration as history events, and notify the responsible actors. Cancellation must not imply the vehicle's defect was repaired or verified.

### Recheck evidence and limits

- Existing targeted regressions: **108 tests / 716 assertions passed** (`TicketProposalWorkflowTest`, `TicketWorkflowTest`, `ExternalShopTicketTest`, `ConvertDueSchedulesToTicketsTest`, `TicketProcessIntegrityTest`).
- Seven isolated investigation probes: **7 tests / 52 assertions** reproduced the observed gaps. They assert defective behavior, not acceptance. Three probes use retained model snapshots to simulate competing decisions; the other four exercise sequential API requests.
- Probe source is ignored local scratch: `tmp/ticket-decisions/TicketDecisionProbeTest.php`. Run from `backend-api`: `php vendor/phpunit/phpunit/phpunit ../tmp/ticket-decisions/TicketDecisionProbeTest.php --no-progress`.
- SQLite in-memory test data only; no live accounts, vehicle records or remote services were modified. No new frontend source changes, browser session or full-suite run was needed for this review.
- The recommended fix order is atomic decision guards and evidence retention, then safe restoration/source ownership, followed by correction/resubmission and cancellation notices.

## Decision-path implementation follow-up — 24 October 2026

TDR-01 through TDR-06 are addressed:

| Finding | Implemented behavior |
|---|---|
| TDR-01 | Decision actions lock and reread vehicle, ticket and work-item state inside their transactions. Terminal work cannot be overwritten through stale model snapshots. Browser decisions carry `expected_revision`; conflicts return 409 and refresh the current record. Logs, notifications and decision history participate in the transaction. Legacy clients without a revision still receive fresh-state checks. |
| TDR-02 | Permanent deletion is limited to untouched mistakes. Notes, dates, recorded cost including zero, files, donor parts, shop events and work history prevent deletion. Users cancel with a reason while retaining the live record and evidence. Older Deleted archives remain recoverable; this release no longer deletes tickets containing work evidence. |
| TDR-03 | Restoration derives its state from preserved work: all submitted repairs return directly to For Verification, with one fresh Custodian verification notice. Unfinished work returns to Active; historical uninspected work remains Open. Retired vehicles and conflicting work prevent restoration. |
| TDR-04 | Proposals, approval, undecline, restoration and historical archive recovery check current report reservations and duplicate work. Old-ticket status propagation cannot reset another current owner's report. Issue links follow the current non-declined/non-cancelled ticket. Resolved or otherwise non-open sources cannot be reclaimed. |
| TDR-05 | Admin can Request Corrections with a reason. The proposal remains Pending Approval, visibly flagged and blocked from approval. Only the assigned Custodian may correct and resubmit the existing proposal for fresh review. Final decline is distinct and does not reserve the fault indefinitely; a new proposal is allowed, but reconsidering an older one must pass ownership/duplicate checks again. |
| TDR-06 | Cancellation requires a reason, preserves it in decision history and notifies responsible staff. Restoration preserves the reason and records a separate history event. A vehicle still at an external shop must be marked returned before its ticket can be cancelled. |

Corrections support the existing work-item titles/types, ticket details and priority, and external reason/scope. Vehicle, repair method and work-item identity are preserved; active or closed repair evidence cannot be rewritten through resubmission. Admin can still final-decline while corrections are pending. Closed work remains permanent, and Custodian independence remains required for repair verification.

### Schema and rollout

- New migration: `backend-api/database/migrations/2026_10_24_000002_add_ticket_decision_history.php`.
- Adds nullable correction reason/time and decision history, plus a decision revision defaulting to zero. It introduces no new availability, condition or readiness status.
- Run `php artisan migrate` from `backend-api` after pulling; refresh the frontend to load the new actions. No reseed or database reset is required.
- Applied locally after a consistent SQLite backup at ignored `tmp/ticket-review/before-ticket-decisions.sqlite`; **5 vehicles before / 5 after**. Existing records do not receive fabricated historical decisions.
- Upgrade/rollback was verified with existing vehicle and ticket data in isolated SQLite. Rolling back removes the new metadata columns, so preserve a database backup before rollback.

### Final evidence

- **522 backend tests / 2,621 assertions passed.** Includes 19 permanent decision-safety cases in `backend-api/tests/Feature/TicketDecisionSafetyTest.php`, the prior ticket-integrity coverage, and a historical archive recovery test.
- Frontend lint/build and PHP syntax checks passed.
- Seven new route-mocked browser checks passed: correction requests, same-ticket mobile resubmission, decline/undecline, cancellation/restoration, deletion visibility with evidence, stale-page conflict recovery, and final decline while awaiting corrections. The eight earlier ticket browser checks also passed. Screenshots were inspected; no browser runtime exceptions were recorded. The expected 409 response was exercised intentionally.
- A migration regression test assumed the operations-removal migration would always be newest. It now exercises that specific migration's up/down cycle, independently of later feature migrations; no removed operations feature was restored to the application.
- New decision tests exercise sequential APIs and retained stale controller-model snapshots. They do not claim a production PostgreSQL parallel-load test. Production scheduler/hosting acceptance, representative-user timing studies, and the six unrelated findings in the broader system audit remain separate work.
