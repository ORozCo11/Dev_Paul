# Workflow Manual Updates — 2026-07-16

Revisions to `VMS_Workflow_Manual_QA_Guide.pdf` (v2.0) from the Main Issue /
Sub-Issue ticket redesign. Implemented in `backend-api` (migration
`2026_07_16_000001_redesign_tickets_for_sub_issues`, `TicketController`,
`TicketSubIssue` model) and covered by `tests/Feature/TicketWorkflowTest.php`.
Replace Workflows 01, 03, and 10 below; everything else in the manual is
unchanged.

---

## 01 (revised) · Staff Reports a Vehicle Defect — Main Issue + Sub-Issues `CUSTODIAN` `ADMIN` `MP`

Someone files a defect; the Admin reviews it and opens a ticket for the
Main Issue. During inspection, the Custodian breaks it down into one or
more Sub-Issues (root causes), each tracked and repaired independently.
The ticket only closes once every sub-issue is Done.

**SCENARIO — QA WALKTHROUGH**

Nicole notices Ambulance AMB-101 running hot. She files an Issue Report.
Roel reviews it and clicks Create Ticket — Main Issue: **Overheating**,
assigning Nicole to inspect. Nicole inspects and finds three root causes:
*low coolant level, faulty radiator, broken water pump* — these are
logged as the ticket's sub-issues. Roel assigns Toto to the coolant and
radiator lines, and Jun (a radiator specialist) to the water pump. Toto
fixes and logs his two; Nicole verifies each → Approved; Roel confirms
each → Done. Jun's line is still at Custodian Check. Roel cannot close
the ticket yet.

**Expected:** ticket reads `Overheating — 2/3`, each line showing its own
state; the ticket stays open (not Done, not Archived) until Jun's line is
also confirmed Done and Roel explicitly clicks Close Ticket. Only then
does AMB-101 return to Available / Good, the linked issue is marked
Resolved, and a maintenance record is written per confirmed sub-issue.

**DATA FLOW**

```
[CUSTODIAN] ---> submits Issue Report ---> vehicle → Needs Inspection --->
[ADMIN] ---> Create Ticket (Main Issue) ---> assigns a [CUSTODIAN] to
inspect ---> inspects, identifies root causes ---> sub-issues logged
(1..N), ticket → Active ---> for EACH sub-issue independently:
  [ADMIN] assigns an [MP] + work order --->
  [MP] fixes & logs --->
  [CUSTODIAN] verifies (Approve / Reject → back to MP) --->
  [ADMIN] confirms ---> sub-issue → Done ---> progress counter → X/N
---> once X = N ---> [ADMIN] ---> explicit Close Ticket ---> ticket →
Closed & Archived
```

**Note:** sub-issues can be appended to a ticket for as long as it's
Active — appending one just grows the denominator (e.g. `2/3 → 2/4`); it
does not spawn a new ticket. Different sub-issues on the same ticket can
go to different mechanics. Closing always requires an explicit Admin
action, even once the counter reads N/N — it never auto-archives. Once
closed, a ticket is permanently locked: no further sub-issue can be
appended (see the Delete/Reopen note near the bottom for the one
narrow exception). A "No Issues" inspection produces zero sub-issues,
which is also eligible for the Admin to close directly.

The vehicle is pulled from service the **moment** the Custodian confirms
"Needs Maintenance" — status and condition move together right there
(2026-07-17 fix). It does not wait for an Admin to actually assign a
mechanic to the first sub-issue; that used to leave a real gap where a
vehicle with a confirmed problem still read Available. A "No Issues"
result likewise clears a stale "Needs Inspection" flag immediately
rather than waiting for the ticket to be closed.

The Custodian also assigns each root cause a **Category** at inspection
time (one category for the whole batch of sub-issues found in that
inspection, or per line if added later via Add Sub-Issue). That category
becomes fixed the moment a mechanic is assigned to that sub-issue — the
Assign Mechanic form shows it read-only, not re-pickable, so a sub-issue
diagnosed as "Engine Repair" can't drift into "Tire Replacement" later.

---

## 03 (revised) · A Sub-Issue Fails Verification (Rework Loop) `MP` `CUSTODIAN` `ADMIN`

Unchanged in spirit from the original workflow — it just now applies to
one sub-issue line, not the whole ticket, so the rest of the ticket's
progress is unaffected.

**SCENARIO — QA WALKTHROUGH**

Toto marks the "low coolant level" sub-issue done. Nicole test-checks it
— still low — and clicks Reject with a note. Toto tops it up again and
re-logs. Nicole re-verifies → Approved; Roel confirms. The ticket's other
sub-issues (radiator, water pump) are untouched by this loop and keep
whatever progress they already had.

**Expected:** on Reject, only that sub-issue returns to Under Repair and
Toto is notified; the progress counter does not move backward for other
already-Done sub-issues. Only after Approve + Confirm does this line
count toward the ticket's total.

**DATA FLOW**

```
[MP] ---> logs sub-issue repair done ---> sub-issue → For Inspection --->
[CUSTODIAN] ---> Reject ---> sub-issue → Under Repair ---> [MP] --->
re-does & re-logs ---> [CUSTODIAN] ---> re-verifies (loops until
Approved) ---> [ADMIN] ---> confirms ---> sub-issue → Done
```

**Note:** the same reject-and-loop exists at the Admin's confirmation
step for a sub-issue — sending it back to Under Repair. This is ordinary
quality control on work that isn't Done yet, not the "reopen a closed
ticket" case described below — those are different things.

---

## 10 (revised) · Multiple Issues on One Vehicle `CUSTODIAN` `ADMIN`

Replaces the old "one active ticket per vehicle" rule. The boundary is
now per Main Issue, not per vehicle — and it depends on whether a
matching ticket is still open.

**SCENARIO — QA WALKTHROUGH**

AMB-101 has an open Overheating ticket (2/3). Two cases:

- **Same Main Issue, still open:** Another overheating-related defect is
  found. It's added to the existing ticket as a 4th sub-issue —
  `2/3 → 2/4`. No new ticket is created.
- **Different Main Issue:** A flat tire is reported on the same vehicle.
  Because "Flat Tire" ≠ "Overheating," it opens as its own separate
  ticket. AMB-101 now carries two open tickets at once; it stays Under
  Maintenance while either is open.
- **Same Main Issue, but that ticket is already Closed:** A new
  Overheating problem shows up next month, after the first ticket was
  closed. Since that ticket is locked, this opens as a brand-new ticket.

**Expected:** the system never silently blocks or queues a new issue —
it either appends it to a still-open ticket for the same Main Issue, or
opens a fresh ticket if the Main Issue differs or the matching ticket is
already Closed.

**DATA FLOW**

```
[ANYONE] ---> files a new issue on a vehicle ---> [SYSTEM] ---> IF a
ticket for the SAME Main Issue is already open (not Closed/Cancelled)
  → append as a new sub-issue on that ticket
ELSE (different Main Issue, or the matching ticket is Closed/Cancelled)
  → open a brand-new ticket
```

**Note:** this is also why a vehicle's "ready to return to service" check
can no longer look at just one ticket. See the new rule below.

---

## New rule · Closing Never Auto-Frees a Vehicle With Other Open Tickets `ADMIN` `SYSTEM`

Not in the original manual — added because a vehicle can now carry more
than one open ticket at a time (Workflow 10 above).

**SCENARIO — QA WALKTHROUGH**

AMB-101 has two open tickets: Overheating (3/4) and Flat Tire (1/1,
confirmed but not yet closed). Roel clicks Close Ticket on Flat Tire.

**Expected:** AMB-101 **stays Under Maintenance** — Overheating is still
open. Only once Roel later closes the Overheating ticket too (after
Toto finishes its last sub-issue) does AMB-101 become Available.

**DATA FLOW**

```
[ADMIN] ---> Close Ticket (only allowed at N/N sub-issues Done) --->
ticket → Closed & Archived ---> [SYSTEM] ---> checks ALL of this
vehicle's tickets ---> IF any other ticket is not Closed/Cancelled
  → vehicle stays Under Maintenance
ELSE
  → vehicle → Available / Good, forecast estimate cleared
```

**Note:** only this Close Ticket action is allowed to move a vehicle back
to Available — never a single sub-issue reaching Done, and never a
ticket's counter merely reaching N/N. This is the single choke point that
keeps an emergency vehicle from being marked ready while a second,
unrelated ticket on it is still unresolved.

---

## Also changed · Delete Ticket, Revisited `ADMIN`

The old **"Reopen an archived ticket"** action was removed for Closed
tickets — a Closed ticket is permanently locked, full stop, no exceptions.
But Delete Ticket was reconsidered separately (2026-07-17): deleting a
ticket that never had any real progress on it (zero sub-issues ever
reached Done) still just discards it with no trace, exactly as before.

Deleting a ticket that **did** have at least one sub-issue already Done
is different — that's real completed work, and an accidental delete of
it is exactly the "accidental click on unfinished work" case reopening
exists for. That kind of delete is archived with `final_status: Deleted`
(distinct from `Closed`) and shows up in Ticket Archives with a **Reopen**
button that restores the ticket and every sub-issue exactly as they were.
A `Closed` archive entry never gets that button — only `Deleted` does.

**SCENARIO — QA WALKTHROUGH**

Overheating ticket on AMB-101 is at 1/2 (coolant fixed and confirmed,
radiator still open). Roel accidentally clicks Delete instead of opening
the ticket. He goes to Ticket Archives, finds it tagged Deleted, and
clicks Reopen.

**Expected:** the ticket comes back as a new ticket ID with both
sub-issues restored — one still Done, one still Open — and the vehicle
returns to Under Maintenance. Had neither sub-issue ever reached Done,
the same delete would have left nothing to find in Ticket Archives at all.

Cancel/Uncancel (for a ticket that was never actually completed) is
unaffected by any of this and still works as before.
