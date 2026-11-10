# Requirements and diagram traceability

Working baseline: 28 October 2026, working tree on `dev-paul` after `20449a7` (see [28 October revision notes](Current-Revision-2026-10-28.md)). These IDs organize the current implementation; they do not replace the supplied academic requirements or certify acceptance. IDs UC-01 to UC-18 are unchanged; UC-19 to UC-21 are new.

| ID | Actor | Use case / requirement | Main entities | Acceptance rule |
|---|---|---|---|---|
| UC-01 | Applicant | Register with staff code, valid ID and photo | User, Barangay, RegistrationSetting | Starts inactive; first Admin needs no code, receives it by email from the Super Admin and enters it at sign-in; later staff need the barangay code and Admin approval |
| UC-02 | Admin (Custodian: add/edit only) | Manage fleet, vehicle types and locations | Vehicle, VehicleCategory, VehicleHub, VehicleLocation | Barangay scope; occupied hub cannot be deleted; Custodian may add a type, add a hub and edit locations but not edit/delete types or delete hubs |
| UC-03 | Admin / Custodian | Register vehicle | Vehicle, VehicleCategory | Editing remains Admin-only; import API uses same role eligibility |
| UC-04 | Custodian | Report vehicle issue | VehicleIssueReport, IssueReportAttachment | Custodian only (`issue.create`); Admin and Maintenance Personnel receive 403; issue-detail visibility requires acceptance retests |
| UC-05 | Custodian | Propose or resubmit ticket (asks about open reports first) | MaintenanceTicket, TicketSubIssue, VehicleIssueReport | Proposal enters Pending Approval; a report held by another open ticket cannot be linked; corrections do not start repair |
| UC-06 | Admin | Review proposal and assign work | MaintenanceTicket, TicketSubIssue, User | Approve, decline or request corrections; no direct ticket creation |
| UC-07 | Maintenance Personnel | Record assigned repairs (standard or with parts from one or more donor vehicles) | TicketSubIssue, DonorPart, Vehicle | Assignment and state guards; one issue report and Needs Repair flag per donor; no self-verification |
| UC-08 | Custodian (Admin approves the proposal only) | Handle external-shop repair: log sent to shop and return, then test | TicketSubIssue, VehicleMaintenanceRecord | The ticket's own Custodian only; Admin never handles the shop; the Custodian then verifies and closes |
| UC-09 | Custodian | Verify repair and close ticket | MaintenanceTicket, TicketSubIssue, VehicleMaintenanceRecord | Independent assigned verifier; approval attests personal testing; rejection identifies rework |
| UC-10 | Custodian | Record condition or confirm readiness | VehicleConditionCheck, VehicleReadinessCheck | Readiness blocked during active repairs; fresh operation attestation required; condition shows Needs Repair as soon as a ticket exists |
| UC-11 | Custodian | Book and edit own schedule | VehicleMaintenanceSchedule | Admin reviews; actual edits return to approval |
| UC-12 | Admin | Review and reassign schedule | VehicleMaintenanceSchedule | Approve/decline; no Admin performance of maintenance |
| UC-13 | Maintenance Personnel | Complete assigned schedule | VehicleMaintenanceSchedule, VehicleMaintenanceRecord | Philippine date validation; verification lifecycle and recurrence apply |
| UC-14 | Admin / Custodian | Manage standalone repair record | VehicleMaintenanceRecord | Admin enters/confirms; independent Custodian verifies |
| UC-15 | Admin | Manage staff, reports and audit | User, UserRole, ActivityLog | Barangay scope; one role per account; last-Admin and open-work guards |
| UC-16 | Super Admin | Manage platform and first-Admin approval | Barangay, User, RegistrationSetting, ConcernReport | No fleet access; includes codes, boundaries and platform support |
| UC-17 | Operational staff | Hide or restore own ticket list entry | hidden_ticket_lists, MaintenanceTicket, User | Personal preference only; work and totals remain intact |
| UC-18 | Scheduler | Convert due approved schedule to proposal | VehicleMaintenanceSchedule, MaintenanceTicket | Requires Laravel scheduler; still requires Admin ticket approval |
| UC-19 | Admin | Create a user with a temporary password | User, UserRole | One role; random temporary password (Admin may type 8+ characters); `must_change_password` set; the Update User page offers no password field (the update API still accepts one) |
| UC-20 | Any account with a temporary password | Set own password at first sign-in | User | API returns 403 `password_change_required` for everything except `GET /user`, `POST /logout` and `PUT /profile/password`; new password must differ; flag cleared on success |
| UC-21 | Custodian / Maintenance Personnel / Admin | View vehicles and fleet catalogs | Vehicle, VehicleCategory, VehicleHub | Barangay scope; Maintenance Personnel see every barangay vehicle but no types or locations pages |

## How to revise the academic artifacts

1. Confirm scope and role alignment in [Must-Haves](Must-Haves.md); retain adviser decisions separately from implementation facts.
2. Edit [ERD source](ERD.md) and [use-case source](Use-Case-Diagram.md), keeping these IDs stable.
3. Check physical keys, nullability, uniqueness and deletion rules against migrations before claiming a physical schema constraint. Model relationships alone are insufficient.
4. Reconcile the overview, capabilities and diagrams together. Use [current revision notes](Current-Revision-2026-10-25.md) for audit retests and verification limits.
5. Regenerate exported PDF/slide material after the working sources are agreed. Existing exports remain older snapshots.

The main model is fleet readiness and maintenance. Do not add dispatch, trips, Usage Log, direct Admin ticket creation, Admin ticket verification, ticket decision-close or Maintenance Personnel issue reporting as current operational requirements. Standalone record confirmation is a separate Admin action. Accounts hold one role; older accounts that still carry two roles remain subject to repairer/handler independence checks.
