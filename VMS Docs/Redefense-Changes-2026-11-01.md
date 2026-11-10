# VMS Redefense: What Changed (1 November 2026)

This document answers the panel's feedback item by item. For each item it says what was done, where to see it, and what is still open.

## 1. Panel feedback and status

| # | Panel feedback | Status | What was done / where to see it |
|---|---|---|---|
| 1 | LGU is the Super Admin; Super Admin creates the Admin; Admin creates staff with temporary passwords | Done | Super Admin: Barangay Management, then View / Manage, then **Create Admin**. Admin: Users, then **Add User**. Nobody types a password. The system makes a random one, emails it, and shows it once on screen as a fallback. The user must set their own password at first sign-in. The temporary password expires after 72 hours. **Resend credentials** (key / mail icon) issues a new one. |
| 2 | Normal map only (no satellite) | Done | The satellite layer and the Map/Satellite toggle are removed from all three maps. |
| 3 | Pagination hidden when there is only one page | Done | The shared pagination control returns nothing when there is one page or less. |
| 4 | No Supabase or cloud storage; everything local | Done | Every upload goes to the server's private local disk. The Supabase disk, fallback code and settings are removed. The 28 files that were on Supabase were copied to local storage with 0 failures. |
| 5 | File size and type limits | Done | Photos: JPG, PNG or WEBP, up to 5 MB. Documents and attachments: JPG, PNG, PDF, DOC or DOCX, up to 10 MB (receipts 8 MB). The form shows the exact size when a file is too big. |
| 6 | View Vehicles revamp with sub-pages | Done | Sidebar: **View Vehicles**, with **Ready to Respond** and **Not Ready to Respond** dropping down under it. Not Ready has the columns Reason Not Ready and **Last Maintained** (last finished maintenance, not the last readiness check), and Missing Parts. |
| 6a | No vehicle photo in tables | Done | Photos show only on the vehicle's own profile. |
| 6b | Dates as plain text | Done | All tables show "Oct 25, 2026" as text. |
| 6c | Assigned-to as a name only | Done | No avatar next to assigned people. The Users table keeps its avatar. |
| 6d | Only necessary columns | Done | Every list now opens with only the essential columns (for example Users: User, Email, Role, Status, Action). IDs, plate numbers, times, notes and similar extras are hidden by default and can be added from the column picker. |
| 7 | Specific errors, not generic | Done | One shared helper explains the real cause: server message, no connection, expired session, no permission, record gone, file too large, too many attempts, server error with its code. |
| 8 | Vehicle categories and history at the end of the sidebar | Done | Both are in a **Reference** group at the bottom. |
| 9 | Merge Condition Monitoring, Report Vehicle Issue and Maintenance Schedule | Done | **Condition Monitoring** is one list of vehicles (Custodian only). Each row has three actions: **View**, **Maintenance Schedule** (a routine job such as an oil change, not a repair) and **Condition Check**. The check form opens already filled with that vehicle. Choosing **Needs Repair** shows the repair form (In-House or External Shop) on the same page. Issue Reports are gone, because the Custodian's condition check is the report. The Admin has no Condition Monitoring. Maintenance Personnel keep a Maintenance Schedule entry because they complete the scheduled work. |
| 9B | Oil change is not a repair | Clarified | An oil change is a scheduled preventive maintenance item. It never goes through Needs Repair. |
| 10 | Maintenance Personnel tests the vehicle, not the Custodian | Done | Submitting a repair requires the Maintenance Personnel to confirm they tested the vehicle (with optional test notes). After a shop returns a vehicle, a Maintenance Personnel tests it and submits it. The Custodian's step is now only "Verified by", then close. |
| 11 | Cannibalization without an inventory of decommissioned vehicles | Done (revised 10 Nov) | No inventory. Parts are logged on the repair ticket; the donor list shows **retired (decommissioned) vehicles only**, as the panel asked, so a donor never changes readiness. Part names come from an add-able list; the same part cannot be logged twice while missing. No Admin approval. |
| 12 | Maintenance finds no repair is needed | Done | **No repair needed** with a required note. The ticket goes to the Custodian to verify and close. No maintenance record is written, because nothing was maintained. |
| 13 | Pin location must be accurate | Done | The pin can be dragged. **Use my location** reads the GPS and only accepts a reading accurate to 30 m or better. The map opens closer (zoom 17, up to 19). The existing boundary check still blocks pins outside the barangay. |
| A1 | Fewer, simpler things | Done | Every page starts with one plain sentence saying what it is for and what to do. Charts, the latest-reports card, and the Category, Capacity, Vehicle, Personnel, Custodian, Domain and date filters were removed from the Tickets, Issue Reports, Users, Categories and Condition pages. The satellite map, calendar-tile dates and avatars are also gone. |
| A3 | Table normalization and naming | Not done | See section 4. |
| A4 | Role clarification | Done | Admin cannot add vehicles (Custodian only). Admin cannot set another user's password. The Maintenance Personnel tests returned vehicles. The Custodian does not test. |
| A7 | "Vehicle type" should be "Vehicle category" | Done | Renamed in all labels and messages. The database already used category. |
| A9 | No barangay registration | Done | Self-registration, registration codes, the Pending Approvals screen, staff codes and the ID/photo upload are removed. The Super Admin creates barangays and their one Admin. |
| A10 | Vehicle profile photo looks bad | Done | One evenly framed 16:10 photo with a clean empty state. |
| A12 | Accidental clicks | Done for key actions | A banner with **Undo** (10 seconds) appears after archiving a vehicle, cancelling a schedule or deactivating a user. Risky actions already ask for confirmation first. |

## 2. How the account flow works

1. The Super Admin (LGU) adds a barangay, then clicks **Create Admin** with the Admin's name and email.
2. The server generates a random temporary password and emails it. Mail settings are in `backend-api/.env`.
3. If the email cannot be sent, the dialog says so and still shows the password once, so it can be handed over in person.
4. The Admin signs in with the temporary password. The system forces a new password before anything else works.
5. The Admin adds Custodians and Maintenance Personnel the same way. A barangay has exactly one Admin.
6. If a temporary password expires (72 hours) or is lost, use **Resend credentials**. The old password stops working immediately.

**Barangay data stays separate.** Every vehicle, ticket and user belongs to one barangay. An Admin, Custodian or Maintenance Personnel only sees their own barangay. The Super Admin sees accounts and barangays, never a barangay's vehicles or tickets.

## 3. How the repair flow works now

1. The Custodian proposes a ticket (in-house, cannibalized or external shop). The Admin approves and assigns.
2. In-house: the assigned Maintenance Personnel repairs, **tests the vehicle**, and submits. Or marks **No repair needed** with a note.
3. External shop: the Custodian records the hand-over and the return. A Maintenance Personnel **tests the returned vehicle** and submits.
4. The Custodian only **verifies** (Verified by) and closes the ticket. The Custodian does not test the vehicle.
5. Cannibalization: a part taken from another vehicle keeps that vehicle Not Ready ("Missing part: ...") until put back, only if the vehicle cannot respond without it.

## 4. Known gaps (honest list)

- **Database normalization and table naming (46 tables):** not rebuilt. The tables are not renamed. Suggested answer for the panel: the schema is documented in the ERD, and a rebuild this close to the defense is a risk. A rename plan is still open.
- **Remaining density:** dashboards now show only the summary cards and one "What needs your attention" list (charts removed). The vehicle profile drops criticality, dates and the duplicate location. The ticket page drops the timeline. Other detail pages were not reviewed.
- **Repair request from a condition check:** Needs Repair shows the repair form inside the Condition Check page. If the check saves but the repair request then fails, the check stays recorded, and a retry would record a second check.
- **Diagrams:** the use case, ERD and architecture images were not regenerated. The architecture diagram still shows Supabase and must be updated.
- **Secrets:** the Supabase keys are still in `backend-api/.env`. Delete the bucket and rotate or remove the keys. Also rotate the SMTP app password if it was shared.
- **Demo accounts:** the database now holds real accounts (for example admin, custodian and maintenance @gmail.com). The old demo logins (admin@barangay.gov and so on) no longer exist, except the Super Admin (superadmin@barangay.gov).
- **Browser checks:** every changed page loaded with no console errors for Admin, Custodian, Maintenance and Super Admin. Create Admin, Resend credentials, No repair needed and the Undo banner were tested by the automated backend tests, but were not clicked through by hand.

## 4b. Later changes (menus, pages and flows)

**Sidebar**
- No group labels. A page with sub-pages has a chevron and folds open and closed. **View Vehicles** holds Ready to Respond and Not Ready to Respond. For the Admin, **Maintenance** holds Repair Tickets, Schedule and Records.
- **Admin:** Dashboard, View Vehicles (with sub-pages), Maintenance (Repair Tickets, Schedule, Records), Users, Reports, Vehicle Categories, Vehicle History, Activity Log. No Condition Monitoring and no Vehicle Location.
- **Custodian:** Dashboard, View Vehicles (with sub-pages), Vehicle Location, Condition Monitoring, My Tasks, Vehicle History. A Custodian adds a vehicle category from the Add Vehicle form, and only the Admin can edit or delete one.
- **Maintenance Personnel:** Dashboard, Vehicles Needing Work, My Work Orders, Maintenance Schedule.
- "Maintenance Tickets" is now **Repair Tickets**, because everything on it is a repair.

**Pages**
- All lists are list view only. Card view is removed from tickets, work orders, schedules and users.
- Date columns say what the date is: Date Completed, Scheduled Date, Date Created, Date Archived, Date Assigned, Date Logged.
- **Ready to Respond** shows Location and Last Maintained (10 Nov; it was Last Readiness Check) instead of Status. **Not Ready to Respond** has no Missing Parts column. A missing part is named in the reason, and the "mark restored" icon is in the Action column.
- The vehicle list has one retirement action, **Decommission**, which needs a reason, with Undo. The Inactive status is removed from the list cards and the filter. The cards are now Total Vehicles, Available and Under Maintenance.
- The mechanic dashboard shows only "What needs your attention".
- Map pins cannot be placed outside the barangay boundary. This is checked in the browser only, not on the server.

**Mark Done (scheduled maintenance)**
- The popup is now a page. The assigned mechanic enters the date, notes, an optional receipt and optional **Parts & Materials** with a cost for each part. The page shows the total cost.
- The record saves the part names and the total cost. It does not save the price of each part separately.

**Cannibalization**
- A part that is still missing from a donor vehicle cannot be taken from it again until it is marked restored. Before this rule, the same transmission could be recorded twice (Alpha Pumper Engine in the sample data still shows it twice).

## 5. Likely panel questions

- **Why are barangays still in the system if registration is gone?** The barangay is what keeps each barangay's data separate. The LGU creates them. They are not self-registered.
- **How does the user get the password if the Super Admin never sees it?** The system emails it. The Super Admin sees it only once on screen, as a fallback.
- **What if the email never arrives?** The dialog warns, shows the password once, and the Super Admin or Admin can resend a new one.
- **Why local storage?** The LGU controls the server and the data. Files are private and only served through an authenticated link.
- **Why can the Admin not add vehicles?** Vehicle registration is the Custodian's hands-on duty. The Admin edits, archives and decommissions.
- **Why does the Custodian not test the vehicle?** The Maintenance Personnel knows how to test it. The Custodian verifies the result and closes the ticket.

## 6. Test status

The full backend test suite passed (605 tests) before the last label and undo changes. New tests cover account creation, resending credentials, expired temporary passwords, one Admin per barangay, no-repair-needed and the mechanic's test requirement. The frontend builds and lints, apart from one older warning in `Workspace.jsx` that is not part of these changes.
