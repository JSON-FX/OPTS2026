# Dashboard proposal directions

Audience and scope were confirmed by the owner: balance office processing and oversight, adapt to role, build directly in code. This is an interactive prototype and Storybook catalog, not an application release.

## Grounded candidates

1. Civic Register — a municipal document register, giving references, ownership, status, and age stable columns.
2. Office Desk — a dispatch desk, putting the current office's incoming/outgoing work closest to hand.
3. Service Board — a public-counter queue board, grouping work by its processing state.
4. Control Room — a municipal operations display, privileging exceptions and cross-office workload.
5. Budget Ledger — a budget utilization statement, making counts and dates easy to reconcile.
6. Route Map — a transit operations board, describing document handoffs and current office ownership.
7. Casebook — a records-room index, pairing a concise register with the selected document's full context.

Concept seed5dcd7a97 assigned candidate7. Casebook is the lead proposal. Civic Register remains the grounded alternative. A third Office Board translates the competitive manual/acetate-tab challenger into familiar work grouping. No direction has approval for live application integration.

## Challenger judgments

- Exposure-record sheets: declined for audience identification and document clarity. Its discipline of distinguishing states without color becomes explicit labels/icons in Casebook.
- Design-annual plates: declined on both axes. Its alignment discipline raises Casebook's row and column rhythm without stamps or decorative registration marks.
- Zoo guide map: declined on both axes. Its immediate wayfinding raises the three-category document navigation, without pictorial lobes in a data tool.
- Manual acetate-tab board: competitive on audience identification; its grouped records remain a full Office Board alternative.
- Cassette j-card: declined on both axes. Its capacity discipline raises explicit counts and bounded activity, without handwriting or a tape metaphor.
- Crouwel grid specimen: declined on both axes. Its rigor raises shared spacing and alignment; visible construction grids are unnecessary.

## Direction contract

THESIS: Dashboard shows where a procurement document is, what needs attention, and what the current role can do. Casebook pairs a queue and detail rather than burying actionable records below repeated summary cards.
OWN-WORLD: daylight office use; cool off-white canvas, white document surfaces, deep blue ink, amber warnings, restrained green completion; Source Sans3 throughout, tabular numerals, thin rules, consistent compact controls, Lucide icons. No hero imagery or decorative charts.
STORY: choose year and office, scan an exception, select a document, inspect its full-name timeline, then follow a permitted processing action. Viewer sees the same facts without processing controls.
FIRST VIEWPORT: a narrow left navigation with account information at its bottom, compact utility header, prominent announcement, Dashboard title and context, horizontal document-count register, concise attention message, workload/activity overview, then the document register and detail. On phones, navigation and account context move into a Sheet; the overview stacks and selecting a record opens its detail. This sequence reflects the owner’s browser comments.
FORM: grounded candidate7, Casebook; seed5dcd7a97. Civic Register and Office Board are proposals with different content structures. The signature interaction is selecting a record to reveal context without losing the queue. Motion is limited to160ms selection/overlay feedback with reduced-motion support.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Product and component references

- Existing Dashboard.tsx, DashboardController, current Browser view: preserve available totals, workload drill-down, activity, stagnant work, turnaround, incident/volume, settings, year and role boundaries.
- GOV.UK table guidance: stable column alignment and captions. https://design-system.service.gov.uk/components/table/
- USWDS table guidance: responsive readable tables and sorting semantics. https://designsystem.digital.gov/components/table/
- Official shadcn MCP: inspected registry, examples, and install commands; new-york-v4 primitives supply accessible component behavior. https://ui.shadcn.com/docs/mcp
- Emil's interaction guidance: frequently used controls respond directly without page-load choreography.
- UI/UX Pro Max: accepted contrast, focus, semantics and touch guidance; rejected its unrelated landing-page recommendation and decorative code typography.

## Honest content

Default fixture counts reflect the local MVP snapshot (3 procurements,3 PRs,2 POs,2 vouchers). All displays are labeled demo fixtures and all interactions are local. Empty/loading/SSO-unavailable states have separate stories. No purchase or payment can occur through these proposals.

## Owner browser feedback revision

The owner requested workload and activity above the document register, a prominent announcement banner, and account information at the sidebar bottom. The revised reading order is announcements, Dashboard context, summary, attention, workload/activity, document register, and volume. Announcement variants are local previews; no realtime service is connected. User information moves into the desktop sidebar footer and mobile navigation footer. This refines the existing Casebook proposal without approving application integration.
