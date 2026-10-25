# Diagram notes and defense talking points

Baseline: **25 October 2026, `352fd0e`**. The system manages barangay fleet readiness and maintenance. Dispatch, trips and Usage Log are outside the current scope.

## ERD

The central chain is **Barangay → Vehicle → Maintenance Ticket → Ticket Sub-Issue → Maintenance Record**. A vehicle also has issue reports, maintenance schedules, condition checks and readiness checks. The supporting sheet adds geography, files, location history, donor parts and platform support.

| Notation / design | Meaning |
|---|---|
| PK | Actual primary-key column in the migrated SQLite schema. Hidden-ticket preferences have a composite user/ticket primary key. |
| FK | Actual migrated database foreign key. A column that looks like an ID is not automatically labeled FK. |
| `?` | Nullable in the migrated schema. |
| `||` at parent | Each child requires one parent at the stored-reference level. |
| `o\|` at parent | A child can have no parent reference or one parent reference. |
| `o<` at child | A parent can have zero or more child rows at the database level. The app can impose stricter workflow rules. |
| Solid relationship | Present in SQLite's migrated foreign-key metadata. |
| Dashed relationship | Logical relationship read/written by code without a migrated database FK. |

The diagram is an implementation overview with selected columns, not a claim that every user-action reference is drawn. The full schema reference lists all physical FKs and delete behavior. PostgreSQL behavior was not tested for this package.

Points to explain:

- `users.role` and JSON `users.roles` implement the role model; there is no invented `roles` or `user_roles` table.
- Vehicle availability, physical condition and readiness evidence are different concepts. A completed repair does not itself attest operational readiness.
- A report can have historical ticket links; the model selects the latest non-declined/non-cancelled ticket. Do not label the stored relationship globally one-to-one just because one current ticket is displayed.
- A schedule's optional resulting ticket/maintenance links preserve provenance. Maintenance-source labels are derived; they are not a separate source table.
- `vehicle_maintenance_records.ticket_sub_issue_id` is a logical origin link without a migrated FK in the inspected schema. It is therefore dashed, not labeled as an enforced FK.
- Hubs are barangay-owned map points. Vehicle `current_location` is not a physical `hub_id` FK; do not invent that relationship.
- Concern History uses `concern_reports.deleted_at` for soft deletion. Personal hidden-ticket preferences are separate from cancellation, closure and archived ticket evidence.
- Managed files store private object references. A free-text URL or path does not create a universal parent-record foreign key.
- `Schema-Reference.md` includes infrastructure and retained rollback tables. Their existence does not create a current dispatch or Usage Log capability.

## Use-case diagrams

Actors are outside the system boundary; ellipses are capabilities inside it. Association lines show participation and have no workflow arrows. Actor rectangles use the UML actor stereotype. The Laravel scheduler is the external timing trigger for scheduled application commands. This view does not invent `include` or `extend` relationships from chronological order.

| Actor | Responsibility |
|---|---|
| Admin | Review proposals, assign work, handle external-shop events, approve schedules, manage fleet/accounts and generate reports. |
| Custodian | Propose tickets, inspect conditions, attest readiness, book schedules and independently verify completed repairs. |
| Maintenance Personnel | Report technical issues, repair assigned work, submit it and complete assigned standalone schedules. |
| Super Admin | Manage platform accounts/barangays/codes, review first-Admin appointments, send activation codes and handle platform concerns. |
| Applicant / visitor | Register, activate a first-Admin account with the emailed code, or submit a public concern. |
| Email service | Deliver the code requested by Super Admin; appointment review itself belongs to Super Admin. |

The current ticket flow is **Custodian proposal → Admin review/assignment → repair → independent Custodian verification → closure or rework**. Admin does not create tickets directly or act as the fallback verifier. Previous mechanics and external handlers cannot verify their own work, including with multiple roles.

Standalone maintenance records have a distinct flow: Admin enters a historical record, an independent Custodian verifies, then Admin confirms. Do not add that final Admin step to ticket closure.

First barangay Admins now register **without a staff code**, remain inactive, receive the code emailed after Super Admin review, then activate using credentials plus that code. An emailed code left unclaimed for seven days expires through the scheduled command. Later staff still supply the barangay code at registration and await their barangay Admin's approval. The email transport and scheduler must be configured for this flow.

The diagrams summarize principal business capabilities. Authentication, notifications, uploads, personal hide/restore, filtering and the dedicated support switcher can be expanded in detailed diagrams; they are not actor permission inheritance. Super Admin has no ordinary fleet workspace access. The dedicated Impersonator utility is a separate support mechanism.

## System architecture

The architecture shows a **browser SPA → Laravel API → relational database/private storage** structure, with separate public and protected API paths. Public registration and activation are validated/rate-limited; protected work uses Sanctum tokens and server-side role, ownership, workflow-state and barangay checks.

| Component | Implemented responsibility |
|---|---|
| React 19 / Vite 8 SPA | Role workspaces, Super Admin portal, forms, maps and Support Center; Axios calls the API. |
| Laravel 13 API | Authentication, validation, scope/permission checks, transactional workflow decisions, models and file-link handling. |
| Relational database | SQLite locally/testing; PostgreSQL is a planned deployment choice, not a verified live host. Stores fleet/platform records and Sanctum tokens. |
| Private storage | Configured private Supabase bucket, with local private fallback. Local deployment storage needs persistence. |
| Mail transport | Activation and account messages; current `AccountMail` uses synchronous sending rather than a mandatory queue worker. |
| Scheduler | Minute-by-minute due-schedule proposals, hourly unclaimed-registration expiry and daily notification pruning. |
| External map / address services | Browser map tiles and location lookup; backend opt-in Photon address suggestions with manual fallback. |

Suggested explanation: a Custodian's proposal travels from the SPA through token and tenant checks to a transaction in the API. Admin approval assigns the repair. The mechanic logs evidence; an independent Custodian verifies it. Closure writes repair history and invalidates prior readiness, and a new physical-operation attestation is required for fresh readiness. The database tracks relationships while private storage holds file bytes.

The presence of authorization components is not a certification of every access path. The dated audit and current revision notes retain separate acceptance retests. No hosted deployment, external SMTP delivery or production storage acceptance was performed to generate these diagrams.
