# Document workspace acceptance

## Authority
Owner approved PR, PO and voucher creation/detail integration on 15 October 2026. Canonical design source is Storybook Hub `projects/opts/src/documents-prototype.tsx`, `documents.css`, `document-controls.tsx`, and `DESIGN.md`. Live data and server permission flags supersede fixture eligibility.

Latest amendments: preview spacing; current year disabled; explicit PR manual creation override; office above account; Help within Settings; remove sidebar system name.

## Visual contract
- THESIS: one document workspace joins its reference, processing history, procurement context and next permitted action.
- OWN-WORLD: approved Casebook, Source Sans 3, cool paper #f4f6f8, blue #234d70, white panels, thin rules, compact Lucide controls.
- STORY: choose an existing fund/supplier or payee, review the provisional reference and actual route, save; then inspect document details and permitted actions.
- FIRST VIEWPORT: narrow sidebar and compact utility header; back link/title/access strip; wide document form or activity panel with compact procurement/route sidebar. On phones, the context follows the main task.
- FORM: extension of approved Casebook candidate 7, seed5dcd7a97; no new visual direction. Standard controls respond immediately; existing overlay motion supports reduced motion.

## Verification boundaries
Browser checks use existing records and unsaved forms. Automated tests use isolated test databases. QR prototype uses synthetic documents and locally simulated actions. Actual scanner hardware and live QR enforcement follow later acceptance.
