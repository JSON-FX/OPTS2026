# Feature: Live document workspaces and QR handoff proposal

## Feature description and user story
As an authorized office user, I want PR, PO and voucher workspaces matching the approved Casebook prototype so I can create and track documents with real workflow data.

## Inherited decisions
The owner approved document creation/detail integration. React 18/Inertia/Laravel remain the app stack. Source authority: Storybook Hub projects/opts/src/documents-prototype.tsx, documents.css, document-controls.tsx and DESIGN.md. The prior automatic-document-references plan defines database locking and annual numbering. Latest annotations supersede the no-manual-PR creation rule. Automatic remains default; an explicit full reference override is allowed. Current year is disabled on automatic creation; historical manual reference strings remain valid. Existing document references remain fixed on edit for PR.

## Scope and acceptance criteria
1. Integrate PR, PO and voucher creation/detail/edit with real data, permissions, prerequisites, workflow and action history.
2. Reference preview has consistent spacing. Current year is disabled. Manual override is explicit and uniqueness checked; annual allocation remains atomic.
3. Assigned office appears above the user. Help moves into Settings. Remove sidebar system-name footer.
4. Update Storybook annotation examples and add a separate QR scan endorsement/receipt proposal. Do not enable live QR enforcement yet.
5. Verify app build, full PHPUnit suite, Hub typecheck/build, desktop/mobile interaction and source sync. Preserve existing dirty work.

## Context references
- resources/js/Layouts/DashboardLayout.tsx: shared approved live shell.
- resources/js/Pages/PurchaseRequests/Show.tsx and PurchaseOrders/Show.tsx: permission and action contracts.
- app/Http/Controllers/{PurchaseRequest,PurchaseOrder,Voucher}Controller.php: real Inertia props and action eligibility.
- app/Services/ReferenceNumberService.php: atomic allocation and historical high water tracking.
- tests/Feature/AutomaticDocumentReferenceTest.php: numbering tests.
- Hub projects/opts/src/documents-prototype.tsx and documents.css: exact visual reference.

## Implementation tasks and ownership
1. Backend worker: requests/controllers/presentation and tests. Add PR manual creation and current automatic year validation, expand eager loaded presentation fields. Preserve role/workflow checks. Validate focused PHPUnit tests.
2. Frontend worker (independent of shared shell): shared Documents components/styles plus nine Create/Edit/Show adapters. Use existing official shadcn primitives and Inertia. Preserve action availability and error states. Validate npm run build.
3. Parent (independent): shared DashboardLayout/Profile annotation changes; Hub preview/year/manual override and shell changes.
4. Parent: build separate scanner-gun keyboard-input QR handoff proposal in Storybook with explicit confirmation, mismatched reference/error states, role/office examples and accessible scan focus. Synthetic local interactions only.
5. Integrate, run gates, publish only local OPTS app/catalog, inspect desktop/mobile, review and record source hashes/screenshots.

## Failure behavior and constraints
Server validation errors remain in forms. Modals close only after success. Stale reference previews are estimates, final number allocated on save. Manual duplicates receive field errors, including soft-deleted references. No SSO infrastructure changes, new framework, schema migration, commit or production deployment.

## Validation commands
- npm run build
- php -d memory_limit=512M vendor/bin/phpunit --colors=never (container runtime)
- Hub: pnpm typecheck; pnpm build:all
- Browser: approved desktop, 1683px, tablet and phone, all three create/detail types, manual toggle, disabled year, real suppliers/routes, responsive sidebar and Settings help.
- QR proposal: correct/mismatched scan, Enter submission, confirmation, role/office blocks and mobile layout.

## Open questions
None blocking: latest request settles UI scope and QR deferral. Scanner prototype assumes keyboard wedge mode ending with Enter; actual scanner hardware acceptance follows purchase.


## Amendments

- 2026-10-15: Owner explicitly requested removing legacy MMO-PO and keeping SSO authoritative. Executed the reviewed, backed-up local reconciliation documented in docs/issues/mmo-po-legacy-mapping.md; no generic SSO merge behavior added.
- 2026-10-15: Browser found timezone-free action history values sorted after creation incorrectly. Emit UTC ISO timestamps from the existing TimelineService and add a regression assertion. Finishing review requires compact mobile overflow control; update the shared document styles in app and catalog.
