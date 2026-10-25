# Current VMS documents

Reviewed 25 October 2026 against `130fe38`. Start with [current revision notes](Current-Revision-2026-10-25.md), then the overview or detailed reference. ERD and use-case SVG overviews now have editable Markdown sources. Existing PDFs, slides and the architecture SVG were not regenerated.

| File | Use it for |
|---|---|
| [Requirements-Traceability.md](Requirements-Traceability.md) | Start here for actor/use-case IDs, requirements, entities and acceptance rules. |
| [ERD.md](ERD.md) | Editable logical ERD, key notes and schema constraints to verify. |
| [Use-Case-Diagram.md](Use-Case-Diagram.md) | Editable actor/action overview and diagram rules. |
| [Documentation.md](Documentation.md) | System overview, architecture, workflow and current audit status. |
| [System-Capabilities.md](System-Capabilities.md) | Detailed module and feature reference; check the audit for known limitations. |
| [Must-Haves.md](Must-Haves.md) | Editable requirements mapping and outstanding acceptance criteria. |
| [VMS-Must-Haves-Updated.pdf](VMS-Must-Haves-Updated.pdf) | Earlier requirements export; consult Markdown for current changes. |
| [Sample-Data.md](Sample-Data.md) | Team setup, demo accounts, five vehicles, sample work and verification results. |
| [System-Audit-2026-10-24.md](System-Audit-2026-10-24.md) | Historical six-finding audit baseline; see current revision notes for disposition. |
| [Ticket-Process-Review-2026-10-24.md](Ticket-Process-Review-2026-10-24.md) | Ticket fields, repair/decision fixes, migration notes and verification evidence. |
| [Claude-Browser-QA.md](Claude-Browser-QA.md) | Claude browser QA skill, full-system test catalogue, findings format and starter prompt. |
| [Branch-Integration-2026-10-25.md](Branch-Integration-2026-10-25.md) | Unmerged V2 branches integrated into dev-paul, conflict decisions and combined verification. |
| [Workflow-Test-Run-2026-10-25.md](Workflow-Test-Run-2026-10-25.md) | Latest ticket/maintenance regression results, real API/browser journey and remaining verification limits. |
| [QA-Run1-Fixes-2026-10-25.md](QA-Run1-Fixes-2026-10-25.md) | Disposition of all 37 browser-audit findings, safeguards, migration and retest evidence. |
| [VMS-Evidence-and-Defense-Review-With-Examples.pptx](VMS-Evidence-and-Defense-Review-With-Examples.pptx) | Latest defense presentation, including examples and speaker notes. |
| [System-Architecture.svg](System-Architecture.svg) | Component diagram. |
| [ERD.svg](ERD.svg) | Database relationship diagram. |
| [Use-Case-Diagram.svg](Use-Case-Diagram.svg) | Role and use-case diagram. |

Older audits, UI reports, geography-review evidence, planning documents and superseded exports live in [the historical archive](../docs/archive/vms/README.md). Their dates and claims describe earlier revisions. They do not supersede the current audit or establish production acceptance.

The latest combined regression result is **612 backend tests / 5,765 assertions**, with **28 frontend tests**, lint, production build and targeted browser/API workflow journeys passing; see [the integration record](Branch-Integration-2026-10-25.md) and [workflow test run](Workflow-Test-Run-2026-10-25.md). Broader audit findings still require their documented acceptance review. Diagrams and older generated artifacts are not a substitute for the current code and verification evidence.
