# Rebuilding the diagrams

Run from the repository root. Backend Composer dependencies and Node.js are needed. Diagram tooling is installed in a temporary directory, not in the SPA's dependency files.

```powershell
$diagramToolDir = Join-Path $env:TEMP 'vms-defense-diagram-tools'
npm.cmd install --prefix $diagramToolDir --no-audit --no-fund @viz-js/viz sharp
php 'VMS FINAL DEFENSE PREPARATION/tools/export-schema.php'
node 'VMS FINAL DEFENSE PREPARATION/tools/generate-diagrams.cjs'
```

The PHP exporter forces a disposable SQLite `:memory:` connection before migration and never runs seeders. `sources/schema.json` contains definitions only. The schema reference and DOT/SVG/PNG/HTML outputs are generated from it. The generator validates the selected diagram column names against the snapshot.

To change the maintained diagram layout/content, edit `generate-diagrams.cjs`, then regenerate. The DOT files are also independently editable with Graphviz; regeneration from the script overwrites them. To use dependencies elsewhere, set `VMS_DIAGRAM_TOOLS` to their installation directory.

Open `Defense-Diagrams.html` in a browser and print to PDF using its CSS page sizes (A3 landscape for ERDs/architecture; A3 portrait for use cases), without browser headers/footers. Save as `Defense-Diagrams.pdf`. The SVGs remain vector graphics in browser print output.

Update the revision labels when preparing artifacts for a later code baseline. The current schema exporter and renderer deliberately label the inspected revision `352fd0e`.
