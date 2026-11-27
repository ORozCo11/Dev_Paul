# QA findings, 27 November 2026

Independent QA pass over the newest work (decommission requests, salvage inventory, shop trip, repair page, scheduled services) and how it behaves with the rest of the system. Tested on the dev database (MySQL) through the API and real browser sessions (desktop, phone, dark). Findings 1 to 7 were fixed the same day (see "Fixed" at the end); 8 and 9 were left as they are.

## Findings (functional first)

| # | Severity | Where | What happens | Smallest fix |
|---|---|---|---|---|
| 1 | Medium | Decommission, schedules | When a vehicle is decommissioned, its booked schedules stay "Scheduled". After the due date they show as overdue ("no job has been started yet") and are counted in the Admin dashboard's **Overdue Maintenance** (went 1 to 2 in the test). The scheduler skips them every minute and writes a hold reason only after its first pass. | When a vehicle is decommissioned, cancel its open schedules ("Vehicle decommissioned") and tell the assigned mechanic. |
| 2 | Medium | Notices | Clicking these notices does nothing (the person stays on the same page): *Decommission Proposed* (Admin), *Vehicle Ready for Salvage* (mechanic), *Decommission Approved* (Custodian). Notices about tickets and the shop trip do open the right page. | Give these notices a destination: vehicle page for the decommission ones, Salvage Inventory for "ready for salvage". |
| 3 | Medium | Mechanic dashboard | After an approval the mechanic gets only a notice. The dashboard has no task to list the salvageable parts. | One dashboard task per decommissioned vehicle whose parts list is not finished. |
| 4 | Medium | Vehicle lists | The Admin and Custodian vehicle lists give no sign that a decommission request is waiting (the row just says GOOD). It shows only on the vehicle page and as one Admin dashboard task. | A small "Decommission requested" mark in the vehicle row. |
| 5 | Medium | Decommission request | A repair ticket can still be opened on a vehicle with a pending request. The Admin's Approve then fails with the open-ticket message only after clicking. | Show the blocker in the request banner ("Close ticket #N first") instead of failing on click. |
| 6 | Medium/Low | Salvage | If a decommissioned vehicle is recommissioned, its parts stay listed as available. (Drawing from an in-service vehicle should be refused by the donor rule; not tested.) | Hide or remove unused salvage lines when the vehicle goes back into service. |
| 7 | Low | Salvage | The same part can be logged twice for one vehicle with different case or spacing ("Alternator" and "ALTERNATOR"), splitting the stock. Names are stored with spaces around them. | Trim names and treat them case-insensitively. |
| 8 | Low | Repair page | The three outcome buttons are radio buttons but the arrow keys do nothing. Tab and Enter work. | Arrow-key support or leave as is. |
| 9 | Low | Deploy | A fresh database seed takes about 2 minutes (the all-barangays seeder alone is about 100 seconds). It completes correctly. | Note it in the deploy steps. |

## Checked and fine
- **Hostile text** (`<script>`, `<img onerror>` in part names, notes, decommission reasons): shown as plain text, no script ran.
- **Other barangays:** another barangay's Custodian, Admin and mechanic cannot propose, decommission, see or change this barangay's vehicle or salvage parts.
- **Races and double clicks:** two repairs taking the last salvage unit at once (one got it, one refused, stock 0, never negative). Double submit of propose, log repair, verify (one record) and approve decommission each took effect once.
- **Input limits:** blank, whitespace-only and 5001-character reasons refused; 9999 accepted, 10000 refused; padded short reasons refused.
- **Decommissioned vehicle:** new tickets and new schedules refused; the mechanic cannot start an existing schedule.
- **Phone:** bottom bar does not cover the last buttons; Salvage Inventory is in the Menu; browser Back after submitting does not reopen the form.
- **Fresh install:** `migrate:fresh --seed` completes with every migration and seeder, no pending migrations.

## Not covered
Real email delivery, the scheduler running on a clock, large data volumes and speed, browsers other than Chromium, screen readers.

## Note
During testing I ran the scheduler command by hand once. It turned the dev database's overdue Bravo Fire Engine schedule into a job. I removed that job and reset the schedule.

## Fixed (27 November 2026)
1. Decommissioning now cancels the vehicle's booked schedules and tells the assigned mechanic. A one-time clean-up (migration) cancels such schedules that already existed.
2. The three notices now open the vehicle (decommission ones) or the Salvage Inventory ("ready for salvage"). The cancelled-service notice opens the schedule.
3. The mechanic's dashboard has a "List salvageable parts" task for each decommissioned vehicle whose list is not finished.
4. The Admin and Custodian vehicle lists show a "Decommission requested" mark.
5. The request banner names the open ticket that blocks approval and disables Approve until it is closed.
6. Recommissioning a vehicle removes its unused salvage lines (used lines stay as history with nothing left) and reopens its list state.
7. Salvage part names are trimmed, and a part can be listed only once per vehicle (case and spacing ignored); renaming onto another line is refused.

Found while fixing: a **declined** ticket proposal counted as an open ticket and blocked decommissioning (the banner said Approve was fine, the server refused). Declined proposals no longer block it.
