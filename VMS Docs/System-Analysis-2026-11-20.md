# VMS System Analysis: Ticketing and Maintenance Schedules (20 November 2026)

> **Status (20 November 2026): phases 1 to 6 and the navigation, record and wording changes below are implemented.** Not done: R7 (removing the hidden issue report that carries "needs repair" after a decline or cancel) and R10 (reducing the number of notification types); both are listed under *Still open* at the end.

Scope: how repairs (tickets) and routine services (maintenance schedules) work today, where the rules, screens and documents disagree, and a simplified end-to-end design that keeps every panel rule. Evidence: the code (`TicketController`, `FleetController`, `ConvertDueSchedulesToTickets`, `config/permissions.php`), the live app with the Hippodromo demo fleet as each role, and the earlier QA report. Nothing was changed while writing this.

---

## 1. Summary

**The system works, but it has two different engines for the same job and too many words for the same state.**

The six biggest problems, in order:

1. **A routine service is approved twice, then treated as a repair.** The Custodian books a schedule, the Admin approves it, and on the due date the system turns it into a *ticket proposal* that the Admin must approve again.
2. **A second, parallel path exists for the same schedule.** A mechanic can complete a schedule directly (before its date), which creates a maintenance record that needs the Custodian to verify and then the Admin to confirm: five steps and three people for an oil change.
3. **Completing a routine service marks the vehicle Needs Repair.** An oil change makes the vehicle look broken until two people sign it off.
4. **Maintenance can be recorded three ways** (ticket, schedule completion, Admin "add record"), each with different approval rules.
5. **About 35 status words** describe the same few facts (a vehicle is fine, being worked on, or waiting for someone). Many are old and no longer reachable.
6. **Readiness expires after 24 hours**, so most of the fleet is "not ready" by default (5 of 13 demo vehicles read "confirmation has expired").

**The fix in one sentence:** one work object (a *Job*) with one lifecycle (Requested → Approved → Done → Verified → Closed). A repair and a routine service are both Jobs. A schedule is only a *planned date* that creates an already-approved Job on its due date. Approval happens once, verification happens once, and the record is written automatically.

---

## 2. How it works today

### 2.1 Repair ticket (works, but heavy)

```
Custodian: Condition Check (Needs Repair) -> proposes ticket      PENDING APPROVAL
Admin: approves + assigns a mechanic per work item               ACTIVE
Mechanic: logs repair, tests, submits                            FOR VERIFICATION
Custodian: verifies                                              CLOSED -> record written
Side exits: Declined, Cancelled, Corrections requested, Return to Admin, No repair needed, rework
```

### 2.2 Routine service (the problem area)

```
Custodian books a schedule                                       PENDING APPROVAL
Admin approves (and may assign a mechanic)                       SCHEDULED
        |
        +-- on the due date (checked every minute) ------------> a TICKET PROPOSAL is created: PENDING APPROVAL
        |                                                         Admin approves AGAIN -> normal repair flow
        |
        +-- OR the mechanic completes it before the date ------> schedule COMPLETED + record FOR VERIFICATION
                                                                  vehicle set to Under Maintenance / Needs Repair
                                                                  Custodian verifies -> Admin confirms -> record COMPLETED
```

Because the due-date job runs every minute, an approved schedule is converted at the start of its date. The direct "complete" path therefore only works for people who finish early, and once the ticket exists the system refuses it ("complete the work there instead").

### 2.3 Words in use (an inventory)

| Object | Values |
|---|---|
| Ticket | Pending Approval, Active, For Verification, Closed, Declined, Cancelled (+ legacy Open) |
| Work item | Open, Under Repair, Pending Approval, For Inspection, For Confirmation, Done, Deferred (four are used) |
| Schedule | Pending Approval, Declined, Scheduled, Completed, Cancelled |
| Maintenance record | Assigned, Under Repair, On Hold, For Verification, Completed + verdict Passed/Failed |
| Issue report (hidden from the UI) | Pending, Under Review, In Maintenance, Resolved |
| Vehicle status / condition | Available, Under Maintenance, Inactive, Decommissioned / Good, Needs Repair (+ legacy Needs Inspection) |
| Vehicle readiness | ready, stale, unchecked, not ready, in maintenance, retired |
| Screens' own words | ticket, proposal, work order, repair log, task, record, schedule, maintenance, service |

---

## 3. Findings

Severity: **H** = wrong or confusing result for a user, **M** = inconsistency or extra work, **L** = tidy-up.

| ID | Area | Finding | Why it matters | Fix | Sev |
|---|---|---|---|---|---|
| F1 | Schedule | A schedule is approved, then auto-proposed as a ticket and needs approval again | The Admin approves the same job twice; the mechanic waits | The approved schedule creates an **Active** job on its date, already assigned (section 4.3) | H |
| F2 | Schedule | Two completion paths (direct complete vs ticket) chosen by timing | Same job, different rules depending on the clock | Remove the direct path; one path only | H |
| F3 | Schedule | Completing a schedule sets the vehicle **Under Maintenance + Needs Repair** | A routine service reads as damage; wrong badges, wrong Not Ready list | A routine service never changes the condition; it only holds the vehicle while the job is active | H |
| F4 | Records | Direct-completed records need Custodian verify **and** Admin confirm; tickets need only Custodian verify | Inconsistent; the Admin does a step the ticket flow does not have | One verification (Custodian), as for tickets | H |
| F5 | Records | Three ways to record maintenance (ticket, schedule, Admin "Add Maintenance") | Three rule sets, hard to audit, easy to bypass verification | Keep one automatic path; the Admin's manual entry becomes "Add past record" (history only, marked as such) | M |
| F6 | Vehicle | Condition, status, readiness and ticket state all describe one vehicle | Users must reconcile four labels (e.g. "Good" yet "Under repair") | One user-facing word: **Condition**; "Under repair" is shown as the reason (done on 18 Nov for the vehicle list) | M |
| F7 | Readiness | Readiness lapses after 24 hours | Most vehicles show "Not ready" without any real change | Readiness stays valid until something changes (a ticket opens, a condition changes) or a configurable limit (suggest 7 days, 24 h only for critical units) | H |
| F8 | Issue reports | A hidden "issue report" is auto-created with every proposal and keeps the vehicle Needs Repair after a decline or cancel (QA-001) | A hidden object controls a visible state | Drop the report as a user concept; derive "needs repair" from the condition check and the ticket | M |
| F9 | Ticket | Legacy states (Open, For Confirmation, Deferred, Needs Inspection) remain in code, filters and docs | Dead options confuse filters and tests | Remove from filters, enums and docs | L |
| F10 | Ticket | Each work item has its own mechanic, repair method and status | A simple repair needs several clicks and approval fields | Default to **one mechanic per ticket**; allow per-item only when needed | M |
| F11 | Condition form | "Maintenance Schedule" is a Condition Result in the dropdown | Planning is mixed with inspecting | Condition Result = Good / Needs Repair only; "Schedule service" is its own button | M |
| F12 | Naming | Ticket / proposal / work order / repair log / task / record / schedule / service | Same thing has 3 names depending on the screen | Use the glossary in section 4.6 everywhere | M |
| F13 | Navigation | Custodian vehicle pages are four sidebar items (View, Readiness Check, Ready, Not Ready) plus Location and History | Same list filtered four ways | One **Vehicles** page with tabs (section 5) | M |
| F14 | Notifications | 17 notification types, a bell, sidebar badges and a dashboard "attention" list all say the same thing | Triple signalling; badges drifted (fixed 18 Nov) | Keep badge + one attention list; reduce notifications to events that need action | L |
| F15 | Admin menu | Maintenance folder holds Repair Tickets, Schedule and Records as three items | Three lists of related work | One **Work** page with tabs (section 5) | M |
| F16 | Schedule | The mechanic's "Complete" asks for cost, parts, vendor and receipt in a pop-up, the repair log asks for the same in another form | Two forms for the same facts | One shared "Finish job" form | M |
| F17 | Process | Mechanic can "Return to Admin" or "No repair needed" with unclear difference | Wrong choice sends work to the wrong person | Keep both, with the one-line explanation under each button | L |
| F18 | Docs | Documents still describe the old Maintenance Schedule menu item, Vehicles Needing Work and 3 completion paths | The panel reads one thing, the app does another | Update with section 4 once approved | L |

---

## 4. Target design (simplified, same rules)

### 4.1 Principles

1. **One work object (a Job), one lifecycle.** Repairs and routine services use the same states and screens.
2. **Approve once, verify once.** The Admin decides what work happens and who does it; the Custodian independently verifies the result. Nobody signs the same thing twice.
3. **The record writes itself.** Closing a Job creates the maintenance record; people never type the same facts again.
4. **Three vehicle words.** Condition (Good / Needs Repair), Ready (checked or not), and the *reason* when something blocks it.
5. **Keep every panel rule:** the Custodian proposes and verifies (never drives or tests), the Admin approves and assigns, the Maintenance Personnel repairs and tests, the performer can never verify.

### 4.2 One lifecycle for every Job

```
REQUESTED  ->  APPROVED  ->  DONE  ->  VERIFIED  ->  CLOSED
 (Custodian)   (Admin: who)  (mechanic repairs and tests)  (Custodian)   (record written)
Exits: Declined (Admin, with reason) . Cancelled (Admin, with reason) . Returned to Admin (mechanic, with reason)
```

Screen wording: Requested, In progress, Awaiting verification, Closed (four stages plus two exits). Internal states (Pending Approval, Active, For Verification) map to these and the legacy ones are removed.

### 4.3 Routine service (schedule) inside the same lifecycle

```
Custodian plans a service (vehicle, type, date, optional mechanic)       REQUESTED
Admin approves it and confirms the mechanic                              APPROVED (a plan)
On the due date the system creates the Job ALREADY APPROVED              IN PROGRESS, assigned to that mechanic
Mechanic does it, tests, submits                                         AWAITING VERIFICATION
Custodian verifies                                                       CLOSED -> record written
If recurring: the next plan is created automatically after closing
```

- **No second approval.** The schedule's approval is the job's approval. (The earlier "bypass" worry in the code applied to unreviewed schedules; today every schedule is Admin-approved.)
- **No direct "Complete" on a schedule.** The schedule only holds the plan; work is done on the Job.
- **The condition does not change** for a routine service (the vehicle stays Good; it shows as "Maintenance in progress" while the Job is active).
- **No Admin confirm** step. Verification by the Custodian is final, exactly as for repairs.

### 4.4 Repair Job details (unchanged where it works)

- The Custodian's Condition Check = Needs Repair opens the request form (unchanged).
- Method per work item: Standard, donor parts, external shop (unchanged). **Default one mechanic for the whole ticket**; per-item assignment appears only under "Assign differently".
- External shop: the Custodian sends the vehicle out; the assigned Maintenance Personnel receives it back, tests it and submits; the Custodian verifies (once your decision on the tester is applied).
- Decline or cancel: the vehicle stays *Needs Repair* with the clear action "Propose again" or "Record Good"; no hidden report involved.

### 4.5 Vehicle state, simplified

| Fact | Shown as | Source |
|---|---|---|
| How is it? | **Condition**: Good / Needs Repair | Custodian's check; changes when a repair closes |
| Can it respond? | **Ready** / **Not ready** + one reason | Readiness check; reason from ticket, missing part or no recent check |
| Why not? | One line: "Under repair: MT-0005", "Missing part: pump", "Needs repair: no ticket" | Derived |

Readiness policy (decision needed, see section 7): keep a check valid until something changes, instead of a flat 24 hours.

### 4.6 Glossary (use these words everywhere)

| Use | Instead of |
|---|---|
| **Repair request** (Custodian creates) | proposal, ticket proposal |
| **Job** (the approved piece of work, repair or service) | ticket, work order, task, maintenance |
| **My Tasks** (mechanic and Custodian lists of Jobs waiting on them) | My Work Orders, Work Tracker |
| **Scheduled service** | maintenance schedule, preventive maintenance, planned upkeep |
| **Record** (history entry, automatic) | maintenance record, ledger |
| **Condition** | status, condition, availability |

---

## 5. Screens: keep / remove / merge / move / rename

### Admin
| Today | Proposed |
|---|---|
| Dashboard | **Keep.** Four KPI cards max + one "needs your attention" list + one graph |
| View Vehicles (+ Ready, Not Ready) | **Merge** into one **Vehicles** page with tabs: All, Ready, Not ready |
| Maintenance folder: Repair Tickets, Schedule, Records | **Merge** into one **Work** page with tabs: **Requests and Jobs**, **Scheduled services**, **History** (records) |
| Users, Reports | **Keep** |
| Vehicle Categories, Activity Log | **Keep** under Reference |
| "Add Maintenance" (record form) | **Rename** "Add past record"; history only |

### Custodian
| Today | Proposed |
|---|---|
| Dashboard | **Keep** |
| Condition Monitoring (tabs Condition Records, Maintenance Schedules) | **Keep**, rename tab to "Scheduled services"; the column "Next scheduled service" stays as the summary. Remove "Maintenance Schedule" from the Condition Result dropdown; add a **Schedule a service** button |
| View Vehicles, Readiness Check, Ready, Not Ready | **Merge** into one **Vehicles** page: tabs All, Readiness Check, Ready, Not ready |
| Vehicle Location, Vehicle History | **Move**: Location becomes a tab on Vehicles; History becomes a tab inside each vehicle's profile |
| My Tasks | **Keep** (cards max 4, chips filter done) |

### Maintenance Personnel
| Today | Proposed |
|---|---|
| Dashboard, My Tasks (Work Orders | Maintenance Schedule) | **Keep** the page; rename tabs to "Jobs" and "Scheduled services"; both lead to the same Job screen |

### Forms and pop-ups
| Today | Proposed |
|---|---|
| Two "finish" forms (repair log, schedule complete) | **One** "Finish job" form: what was done, parts, cost, files, tested checkbox removed (submit = tested, as already done) |
| Cancel buttons red, Approve green | **Keep** (done 18 Nov) |
| Status dropdowns | **Replace** with coloured chips (done for My Tasks; do the rest) |

---

## 6. Rule changes (what the system must do differently)

| # | Change | Where |
|---|---|---|
| R1 | A due, approved schedule creates an **Active** job assigned to its mechanic (no Pending Approval) | `ConvertDueSchedulesToTickets` |
| R2 | Remove the schedule "complete" action and its record creation; the schedule shows the Job's progress | `FleetController::completeSchedule`, routes, UI |
| R3 | A routine service never sets condition to Needs Repair; it only marks the vehicle "maintenance in progress" | `VehicleOperationalState::hold`, ticket creation for services |
| R4 | One verification (Custodian) for every Job; remove Admin "confirm" for records | `confirmMaintenance`, `record.confirm` |
| R5 | Job close writes the record automatically (already true for tickets; extend to services) | `TicketController` close path |
| R6 | Readiness validity driven by events, with a configurable time limit | `readinessStateFromCheck`, `READINESS_FRESHNESS_HOURS` |
| R7 | Stop creating hidden issue reports; decline/cancel leave an explicit "Needs repair, no request" state | `TicketController::propose`, decline/cancel |
| R8 | Default one mechanic per ticket; per-item override optional | approval form and `approveTicket` |
| R9 | Remove legacy states (Open, For Confirmation, Deferred, Needs Inspection) from filters, enums and docs | models, controllers, UI, docs |
| R10 | Collapse notifications to the events that need a decision | notification senders |

---

## 7. Decisions needed from you

1. **Readiness validity:** keep 24 hours for everyone, or until something changes (my recommendation), with a limit such as 7 days for critical vehicles?
2. **Scheduled service approval:** confirm that the Admin's approval of the schedule is the only approval (the job starts already approved).
3. **Admin "add record":** keep it as "Add past record" for history, or remove it?
4. **One mechanic per ticket by default:** acceptable, or must every work item always carry its own mechanic?
5. **External shop:** how is the receiving Maintenance Personnel chosen (the Admin picks at approval as "Assigned To", or any mechanic takes it)? This is still open from your last message.
6. **Vehicle Location and Vehicle History** as tabs: acceptable to remove them as sidebar items?

---

## 8. Build order (small steps, safest first)

| Phase | What | Risk | Check that it worked |
|---|---|---|---|
| 1. Words | Glossary applied to labels, filters, docs; remove legacy states from filters | Low | Screens show only the new words; filters list only live states |
| 2. Vehicle page | Merge Ready / Not ready / Readiness / Location into tabs; History into the vehicle profile | Low | Same rows and actions as before, fewer sidebar items |
| 3. Condition form | Remove "Maintenance Schedule" from Condition Result; add "Schedule a service" button | Low | Condition Result has two options; the button opens the schedule form with the vehicle chosen |
| 4. Schedules into Jobs | R1, R2, R3: approved schedule creates an Active job; remove direct complete; no Needs Repair for services | **Medium** (changes rules; needs tests) | Approve a schedule, advance its date: an Active job appears for the mechanic with no second approval; the vehicle stays Good |
| 5. One verification | R4, R5: Custodian verify closes and writes the record; remove Admin confirm | Medium | A closed job has a record; no record waits for the Admin |
| 6. Readiness | R6 with the chosen policy | Medium | Ready vehicles stay ready until an event; "expired" appears only past the limit |
| 7. Clean hidden state | R7, R8, R9 | Medium | Decline then "Record Good" clears with no hidden report; legacy values gone |
| 8. Admin menu | Merge Repair Tickets, Schedule, Records into **Work** with tabs | Low | One sidebar item replaces three |

Each phase ships with tests and a browser pass as each role, as was done for the earlier QA rounds.

---

## 9. What was verified and what was assumed

**Verified in the code and the live app:** the due-date conversion (every minute, creates Pending Approval); the direct complete path and its verify and confirm steps; the Needs Repair hold on completion; the three ways to record maintenance; the status inventory; the 24-hour readiness window; the demo data (5 of 13 vehicles with expired readiness).

**Assumed (confirm in section 7):** that the Admin's approval of a schedule is meant to authorise the later job; that history-only manual records are still wanted; that per-item mechanics are rarely needed.

**Not tested here:** concurrency under load, email delivery, and the Super Admin pages (out of scope for this review).

---

## 10. What was built (20 November 2026)

| Item | Done |
|---|---|
| R1 Approved schedule creates an Active job (no second approval) | Yes, with tests |
| R2 Direct schedule "complete" removed (route, ability, UI) | Yes |
| R3 Routine service never sets Needs Repair | Yes |
| R4/R5 One verification, record written at close; no Admin confirm, no manual records | Yes (`record.create` and `record.edit` have no role; the Add Maintenance form is gone) |
| R6 Readiness event-driven, 7-day limit | Yes (`READINESS_MAX_DAYS`) |
| R8 Admin assigns (per item still possible) | Unchanged by decision |
| Navigation: Vehicles tabs, Admin Work tabs, history on the vehicle profile | Yes |
| Condition form: Good / Needs Repair only; Schedule a service is its own form | Yes |
| Wording: Scheduled services, Assigned Maintenance Personnel | Yes |

**Still open:** R7 (the hidden issue report still carries the "needs repair" finding after a decline or cancel; the Custodian clears it with a Good check, as in QA-001), R9 (legacy states remain in the database enums but are not shown), R10 (notification types not reduced).
