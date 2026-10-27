# VMS Final Defense Preparation

Prepared **25 October 2026**, from merged development revision **`352fd0e`**. This package reflects the SMTP activation, Support Center and dashboard-request branches as well as the maintenance workflow.

| Diagram | Open / edit | Insert into slides |
|---|---|---|
| Entity-relationship diagram | [ERD.svg](ERD.svg) | [ERD.png](ERD.png) |
| Use-case diagram | [Use-Case-Diagram.svg](Use-Case-Diagram.svg) | [Use-Case-Diagram.png](Use-Case-Diagram.png) |
| System architecture | [System-Architecture.svg](System-Architecture.svg) | [System-Architecture.png](System-Architecture.png) |

Use the SVGs for sharp zooming and editing. PNGs are 3,200 pixels wide. [Defense-Diagrams.html](Defense-Diagrams.html) opens all diagrams locally and includes print layouts.

Supporting sheets keep the primary diagrams readable:

- [Supporting ERD](ERD-Supporting.svg): geography, documents, donor parts, audit and platform support.
- [Platform use cases](Use-Case-Platform.svg): first-Admin email activation, later staff registration, public concerns and Super Admin administration.
- [Diagram notes](Diagram-Notes.md): scope, cardinality interpretation and points to explain during defense.
- [Schema reference](Schema-Reference.md): all 44 migrated table definitions, columns and foreign keys; no application records.

The ERD was checked against a **fresh in-memory SQLite database** created from the migrations. The existing fleet database was not migrated or read. Field lists in the diagrams are abbreviated, and selected relationship lines are omitted for readability. Dashed relationships exist in code without a migrated foreign key. The architecture separates current local implementation from proposed PostgreSQL deployment.

For requirements, retain [Must-Haves](../VMS%20Docs/Must-Haves.md) and [system capabilities](../VMS%20Docs/System-Capabilities.md). Their older onboarding wording should be read with the merged activation flow explained in this package. These diagrams do not amend the supplied academic baseline or close outstanding audit findings.

To regenerate the package, see [tools/README.md](tools/README.md). Everything in this folder is newly created; the earlier diagrams in `VMS Docs` remain available.


The ERD, both use-case sheets and the architecture sheet are drawn by `tools/generate-capstone-style.cjs` in the style of the SkinSense capstone paper (navy entity tables, crow's-foot lines with verb labels, stick-figure actors, layered architecture). The Supporting ERD still uses the earlier Graphviz look until regenerated. The old Graphviz DOT sources for the redrawn sheets and the outdated PDF were removed; print `Defense-Diagrams.html` to PDF to rebuild it.
