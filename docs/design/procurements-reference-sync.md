# Procurements approved reference and live integration

Saved 15 October 2026. Owner approved the latest Storybook Procurements prototype for live integration with all interactions, real data, preserved SSO access/workflow rules, and shared navigation/style retirement.

## Authoritative references

- [Standalone prototype](https://storybook.lan/opts/prototypes/procurements.html)
- [Office search focused](https://storybook.lan/opts/?path=/story/proposals-procurements--office-search-focused)
- [Particular search focused](https://storybook.lan/opts/?path=/story/proposals-procurements--particular-search-focused)
- Hub source: `/Users/jsonse/Documents/development/storybook-hub/projects/opts/src/procurements-prototype.tsx` and `procurements.css`.
- Immutable snapshot: `docs/design/procurements-prototype.html`.

The catalog has 19 Procurements stories, 102 total OPTS stories. Both dedicated focus stories mount with the intended search focused. The parent dialog does not steal focus. Each search row has one inset 2px indicator; the inner input has no external outline or shadow. Source CSS is preserved. Only OPTS was published.

## Source SHA-256

| Artifact | SHA-256 |
| --- | --- |
| Hub prototype TSX | `26e8f82a27b979a1fd17173cb56c7f54200470b81a8cc0d6589daeef9d47fa17` |
| Hub prototype CSS | `622d6c4b417258e6cab3080f61327ad54c1ce09d51b7571ef1cbb6befbb97558` |
| Hub stories | `35df16876c78699586993d9d9363194fb5546a40d0086a1340331fcf942a1f68` |
| Standalone source / built / container / HTTPS / app snapshot | `18675130205d0a5b8733cf0f49e86e7fa2342926f7996c4378e742f70a14d4bd` |
| Published catalog index | `a1e27dfb34893104d4323a28c5265850728ff7a4c00869f53c233e37102ad75d` |
| Live workspace TSX | `b7c44fafdc6fccc1ce42d9688d8755258442ee710a255c341bd80e00750254a8` |
| Live procurement CSS | `785f723df28ee4f8ee055c4c20bcdedc6cf8b3ab88fab3118edbfa0cacfc21ed` |

## Application adaptations

The approved composition, spacing, typography, responsive cards, dialogs and motion were ported to existing React 18 / Tailwind 3 / official shadcn primitives. Explicit data-slot attributes and HSL semantic variables bridge the Hub's React 19 / Tailwind 4 output. Live data replaces fixtures through Inertia props and existing resource routes.

The application omits proposal-only demo controls, fixture roles and reset actions. Directory options use active SSO-mapped office IDs; abbreviations are labels and full names remain search keywords. Historical transaction names remain visible. Server date determines the form default and maximum. Reporting year is in real Settings alongside the existing SSO profile; only years present in the application are offered. These are real-data adaptations, not new workflow rules.

## Interaction parity checklist

- [x] Search purpose, ID, office, particular and linked document reference; debounce, loading, reset and server error recovery.
- [x] All / In progress / Completed / Cancelled tabs and selected-year global counts.
- [x] Office, particular, status, date range and created-by-me filters; sorting, page size and pagination.
- [x] Persistent per-user columns; any non-action row area selects preview; narrow-screen selection opens quick view.
- [x] Arrow quick view, action menu, full detail and return-to-register context.
- [x] Real create/edit submissions, field errors, saving state, success feedback, fixed targets and linked-document field locks.
- [x] Centered animated create/edit/quick/document/archive/help dialogs; Escape/dismissal, focus restoration and reduced-motion rules.
- [x] Searchable office/particular comboboxes with corrected full-row focus indicator.
- [x] Full procurement detail, actual linked document states, eligible creation links, workflow overview and document links.
- [x] Full-name actual activity timeline, actor/date/notes/reason, document-type filter, creation events and expandable status history.
- [x] Existing soft archive confirmation, cancellation and preserved linked transactions.
- [x] Shared collapsible/persistent sidebar, mobile drawer, real notifications dropdown, account, help and Settings.
- [x] Responsive desktop, tablet and phone wrapping with no page/modal horizontal overflow in checked states.

Browser evidence and limitations are documented in `.claude/reports/procurements-live-integration-report.md`. This is a local implementation, not a production release or a new full procurement endorsement lifecycle run.

## Annotation revision, 15 October 2026

Status badges now retain their 4px icon gap and intrinsic width in every context. The register and preview have independent content heights, eliminating filler below pagination. Checkbox sizing overrides the shared button minimum. Timeline controls retain intrinsic width and wrap below copy on phones. Office facts show abbreviations with full-name tooltips; initial quick-view focus lands on its title. See `.claude/reports/2026-10-15-ui-annotations.md`. Current hashes above supersede the earlier integration snapshot.
