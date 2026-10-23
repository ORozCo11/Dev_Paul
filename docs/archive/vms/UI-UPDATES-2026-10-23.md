# UI updates — 23 October 2026

This reference records the UI changes made on 23 October 2026. The branches below were pushed to the `v2` remote (V2-VMS). A pushed branch is not evidence that its changes have been merged or deployed. Some changes are on separate branches; consult the branch list when reviewing a checkout.

## Tickets and public footer

Branch: `ui/ticket-views-footer`.

- Maintenance Tickets opens in List View. Card View can be selected while using the page.
- Default columns: Ticket ID, Created, Vehicle Image, Ticket Title / Vehicle, Priority, Status, Assigned To, Sub-Issues, Next Step, Actions. Users can customize columns.
- Ticket ID uses the ticket status color, with a smaller date underneath in MM/DD/YYYY format and no time. The date uses the last update, falling back to creation. Created remains a separate date column; it is not a due date.
- Assigned To shows avatars; names are available through the avatar tooltips and accessible text.
- Sub-Issues shows completed/total counts and a percentage bar. Next Step uses the workflow stage label and color. Status, priority and Next Step have compact square badges sized to their contents.
- Card View places the vehicle photo beside the title, vehicle name and plate number. Progress and the workflow stage appear above the status, priority, creation date and assignees.
- The public footer tagline is “A System to Manage your Barangay's fleet”. Copyright is centered, policy links align right on desktop, and the footer Support Center link is removed. Smaller layouts stack the footer content.

## Shared badges

Branch: `ui/square-badges`.

Shared status badges, priority labels, workflow labels, counts and other capsule labels have square corners. This includes vehicle availability/readiness labels. This change concerns badges and capsules; it does not make every container or avatar square.

## Vehicle types and Custom Fields

Branch: `ui/custom-fields-layout`.

The Custom Fields editor was initially restyled and subsequently removed from Edit Vehicle Type. The final branch removes that section and its unused component/styles. It does not delete stored fields or values, remove backend custom-field endpoints, or remove existing custom-field handling from vehicle forms and import.

Branch: `ui/vehicle-type-overview`.

The Vehicle Type Overview appears above the vehicle type table. It shows the number of matching vehicle types, total vehicles assigned to those types, and unused types. Labeled bars show populated types in descending vehicle count; unused types are listed separately. Counts follow the current table search/domain filters. “Total vehicles” is a category assignment count, not a readiness or availability count.

## Maps and location summaries

Branch: `ui/map-labels-default`.

Satellite place labels load automatically on Vehicle Location and Add Location. The Map labels toggle is removed. The Map/Satellite switch remains on Vehicle Location. Provider place labels do not define the official barangay boundary.

Branch: `ui/vehicle-location-summary`.

Three compact cards appear outside the map panel, above the map tabs when Vehicles Map is selected:

| Card | Meaning |
|---|---|
| Total vehicles | Number of vehicles supplied to the map for the current workspace scope. |
| Total hubs | Visible hubs with valid coordinates. Hidden hubs are excluded. |
| Vehicles without a hub | Vehicles whose current location does not match a visible hub using the map's existing matching rules. |

Hub-dependent counts show a dash until hubs load. These cards do not count location-history rows or represent live GPS tracking. The map may place unmatched vehicles at its fallback point; this does not mean they have a matching hub.

## Reports

Branch: `fix/report-text-layout`.

The seven report types share the corrected table styles: Inventory, Type, Location, Issue, Maintenance, Maintenance Schedule and History. Words wrap at normal word boundaries rather than splitting at arbitrary letters. Table spacing and font sizing are increased, and ID headers use uppercase “ID”.

Reports with more than four columns use landscape format. Wide landscape previews retain a minimum document width and scroll horizontally on smaller screens. Print layout removes that screen minimum and uses the landscape page size. CSV export remains available.

Verification performed: representative column rendering and CSV value generation for all seven report types, empty-report handling, production build and whitespace checks. Browser appearance, actual print/PDF pagination and deployed-data acceptance have not been visually verified.

## Header account selector

Branch: `docs/ui-updates-account-selector` (documentation and province selector update).

The header account selector change removes “Platform accounts” from the province dropdown. Only accounts with a province ID and name contribute province options; a platform account does not preselect a hidden province entry. This is a frontend selector change and does not change backend impersonation permissions.

## Verification and artifacts

Production builds passed after the UI changes. No database migration is required for these presentation changes. Generated PDF/Word/HTML documentation has not been regenerated; use these Markdown references for the updated UI behavior.
