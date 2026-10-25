# VMS test catalogue

This is a coverage seed, not evidence that features exist or work. Verify current requirements and add discovered features. Expand applicable rows into actual case IDs; one click cannot pass a row. Distinguish deliberate removals from missing requirements. Use disposable fixtures for irreversible transitions.

## Actors and fixtures

Prepare when available: Admin A; Custodians A1/A2; mechanics A1/A2; dual-role Custodian+mechanic; unrelated staff in barangay B; Super Admin; pending/inactive accounts. Missing actors become coverage gaps, not substitute identities. Tabs may share one login.

Prepare synthetic vehicles covering Available, Under Maintenance, Inactive and Decommissioned; linked/unlinked issues/condition checks; proposals for each repair method; multi-item repairs with different mechanics; closed/cancelled tickets; standalone/converted schedules; harmless text/image/PDF fixtures. Do not overwrite shared demo vehicles without scope. Record original states, current owners, previous repair participants and linked IDs.

For each transition compare identity, status, permissions, data, linked records, notices, audit/history and the three vehicle indicators before/after.

## Access, onboarding and platform

| Family | Required scenarios |
|---|---|
| AUTH | Valid login for each role; wrong/blank credentials; pending/deactivated messages; refresh/logout; protected URL/back after logout; expiry/revocation with safe fixtures. Do not brute-force rate limits. |
| REG | Location cascade/code validation; first Admin vs later staff approval; initial inactivity; duplicate email; required/invalid fields. No real barangay registration or code exposure. |
| USER | Own-tenant Admin list/create/edit/approve/roles; profile/avatar/password validation; active assignees; last-Admin, self-deactivation and open-work guards with disposable users. Existing-session role changes if authorized. |
| IMP | Names/placeholders/cascade; start/reason/end and original identity; no Super Admin targeting; local/deployed availability/read-only policy. Do not sign work as absent staff. |
| SUPER | Barangay list/create; first-Admin approval confirmation/rejection; recovery/roles/codes; boundary lookup/candidate/map/confirm/discard/not-found and verified overwrite prevention; concerns; account-only activity; fleet URLs denied. Classify third-party failures carefully. |
| PUBLIC | Landing/about/support/privacy links; safe required/invalid concern inputs; success/error recovery. No live external reports/messages without scope. |

## Fleet and supporting modules

| Family | Required scenarios |
|---|---|
| VEH | Authorized registration/edit; Land/Water fields; type custom fields; optional clearing; duplicate plate; invalid year/capacity; photo display; list search/filter/sort/pagination; detail links/reload. |
| IMPORT | Template; valid/mixed-error preview with no records saved; confirm once; file/size/row boundaries; duplicates within file/database; custom fields; error report/summary matches saved records. |
| TYPE | Domain/criticality; custom field types/required/order/archive preserving values; in-use deletion/denied roles. Shared edits and cross-tenant effects need explicitly authorized fixtures. |
| MAP | Vehicle/hub accuracy; basemaps/boundary/fallback; pin/coordinate validation; permitted hub add/edit/remove/restore; location history; focus/fullscreen/download; tile/geocoder recovery. |
| FILE | Vehicle/issue/repair/avatar valid and safe disallowed-type/oversize files; ownership/edit/delete; view/download/reload; known unauthorized QA file links; redact signatures. Content validation/expiry is untested unless exercised. |
| HISTORY | Tested events appear once with correct actor/role/time/record; filters/export/print; no trip events; cancellation retains evidence. |
| COND | Custodian create/edit-own; other-user denial; Admin deletion/reversion if authorized; condition effects; ticket/schedule prefill; linked current state vs historical observation. |
| READY | Type-specific/custom checklist; mark-all; any failed item fails; ready/stale/unchecked/maintenance/retired; mark-available eligibility; old/fresh fixtures. Do not fake time or merge availability/condition/readiness. |
| RETIRE | Role/reason/open-work guards for archive/decommission; restore respects unfinished work; retired vehicles protected from accidental activation. |
| PARTS | Different same-tenant donor/recipient; quantities/dates/eligibility; both histories; donor issue/condition; readiness evidence after physical removal. |

## Ticket decisions and repairs

Verify these transitions against current approved requirements. Disagreements require investigation and sources, not invented rules.

| ID | Actor/state | Scenario and checks |
|---|---|---|
| TKT-01 | Custodian/new | In-house/external proposals; required work items; optional Repair Type; vehicle/photo/details; priority/cost; external reason/scope; generated title; optional links. Confirm required fields per method. |
| TKT-02 | Custodian/source link | Prefill, same vehicle, one live source owner, duplicate prevention, final-declined replacement vs reconsideration; no partial save on rejection. |
| TKT-03 | Admin/Pending Approval | Review edits; each in-house item assigned to active mechanic, multiple mechanics; missing assignment denied; approve once to Active; external requires no irrelevant mechanic. |
| TKT-04 | Admin/Pending Approval | Blank decline reason denied; Declined preserves record/reason/notices and linked report; undecline only to Pending Approval; source/duplicate/retirement guards. |
| TKT-05 | Admin to Custodian | Corrections with reason, stays Pending Approval; only assigned Custodian resubmits same IDs/source; fresh Admin review; final decline during corrections; repeat cycle. |
| TKT-06 | Mechanics/Active | Own-item notes/parts/cost/file; no irrelevant removed dates; first item does not hand off whole ticket; last item to For Verification with one notice; persistence/double-click behavior. |
| TKT-07 | Admin/live work | Mechanic/Custodian reassignment and required reason; old actor loses task, new gets it; previous participants and partially completed evidence retained. |
| TKT-08 | Custodian/For Verification | Approve needs test attestation/no failed checks; closes, writes each ledger item, resolves issue, completes linked schedule, updates fleet/notices. Wrong Custodian/Admin/current or prior repairer denied, including dual-role. |
| TKT-09 | Custodian/For Verification | Return needs failed check/reason; selected items rework, others handled per current rule; correct mechanics notified; re-log/reverify retains notes/cost/files. |
| TKT-10 | Admin/unfinished approved | Cancel reason, evidence retention, external still-out guard, report/schedule/fleet effects. Restore unfinished to Active, fully submitted to For Verification; conflict guards; one fresh task. |
| TKT-11 | Admin/delete/archive | Untouched mistake deletion vs refusal with notes/cost including zero/parts/file/shop event; failed delete stays on page. Closed immutable; historical Deleted recovery differs from reopening Closed. |
| TKT-12 | Stale views | Newer approve/decline/cancel/reassign/close followed by old submission: conflict/refresh, no overwritten or duplicate effects. Not simultaneous-transaction proof. |
| EXT-01 | Admin/Active external | Provider/reason/scope/estimate; send out; no submission before return; outcome/actual cost/warranty; independent verification. Shop sending is not operational dispatch. |
| EXT-02 | External rework | Failed work to repeat shop cycle to receive/verify; earlier providers/visits/cost/files retained; handlers cannot self-verify; ledger not lost/double-counted. |
| CAN-01 | Cannibalized item | Donor/parts persist; editable before logging, locked after; at least one valid part; one donor issue listing parts; no invented donor cost; donor readiness reviewed. |

Also test detail next actions/history, status defaults, All statuses, correction labels, row actions and archives. Removing the list's Sub-Issues column/progress ring must not remove actual work items/handoffs.

## Preventive maintenance and ledger

| Family | Required scenarios |
|---|---|
| SCH-BOOK | Custodian books Pending Approval; conflict warning allows deliberate confirmation; optional assignee; recurrence 1-60 months; date/time/location; creator edit/delete; Admin approve/decline reason/undecline and notices. |
| SCH-DONE | Assigned mechanic clicks/keyboard-opens Mark as Done on approved unconverted schedule; submits to For Verification record; wrong person denied; future-date rule. Inspect overlay/modal/validation/response before blaming API. |
| SCH-TKT | Due job creates exactly one Pending Approval proposal, not Active, suggestion only. Converted active ticket prevents inappropriate schedule edit/cancel/duplicate completion; clear linked ticket; closure completes once. Job execution requires tool/operator evidence. |
| SCH-RECUR | Standalone completion and linked ticket closure each create one next occurrence; month-end/leap-year/interval/duplicates need suitable fixtures, not ordinary-date inference. |
| LEDGER | Ticket/schedule/historical origins; vehicle grouping retains all items/totals/files; search later repairs; origin-specific verify/confirm; direct create/edit cannot bypass checks; changed evidence invalidates verification where required; Completed locks. |

## Shared behavior and resilience

| Family | Required scenarios |
|---|---|
| NOTICE | Reachable notifications despite intentional bell/menu removal; read/unread/delete if available; exact links/ownership; no duplicate notices. Pruning needs job evidence. |
| DASH | Fixture counts/coverage/criticality/readiness/risk/forecast/totals before/after; links/filters; zero/partial loads; hidden presentation vs missing required computation. |
| REPORT | Every offered report; filters/status/date/search; preview/print/export match data/tenant/role; amounts/Philippine time. Benign =1+1 text on authorized fixtures checks CSV bytes if tools allow; never execute untrusted spreadsheet content. |
| AUTHZ | Every sensitive module: own/other assignment, same/other tenant, inactive/unrelated role, Super Admin; list vs known detail/file/nested metadata/action. UI denial alone leaves server control unverified. No arbitrary IDs. |
| UI | Desktop/narrow mobile, light/dark, keyboard/focus/modals/errors; icon labels/tooltips/readability; dropdown search vs creation; long text/names/photos; action gaps/status-filter alignment/overflow. Numeric contrast needs measurement. |
| RESILIENCE | Empty lists, invalid inputs, form preservation, loading/retry, offline/expiry if supported, back/forward/deep link, draft/reload, double submit and ambiguous success. No stress tests on shared systems. |
| SCOPE | Removed Dispatch/Dispatched/Usage Log/trip UI; stale docs/diagrams, unintended role actions, resurrected removed UI, orphan links/actions. No link does not prove backend removal. |

## Historical leads and completion

Revisit supplied old audit leads: file signing, issue ownership, global catalogue authority, donor readiness, CSV formula handling, future completion dates. Record reproduced/not reproduced in executed case/fixed with evidence/blocked/not tested on the current build. Expanded catalogue permissions need policy/tenant review, not automatic rejection because an old test expected denial.

Every discovered module/action needs cases or a reasoned exclusion. Count statuses and fully/partly/not-tested modules; define denominators. Opening modules or sampling one path never proves full coverage. Cases blocked by a known defect remain blocked.
