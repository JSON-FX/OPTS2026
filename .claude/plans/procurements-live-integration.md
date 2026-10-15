# Procurements live UI integration

## Goal and authority

Implement the latest approved Storybook register, full detail, forms, quick view, document overview, archive confirmation and every interaction with real application data. Replace the old authenticated navigation/theme with the approved Dashboard shell. Keep SSO roles, eligibility checks and workflow/business rules authoritative on the server. Owner explicitly approved implementation and navigation/style retirement on 15 October 2026.

The approved prototype and prior decisions settle the scope; no open product decision requires another question. Existing Laravel 12, Inertia React 18, Tailwind 3 and installed official shadcn components remain the architecture. No schema changes or new REST API. No commit or remote release requested.

## Reference and existing contracts

- Hub `projects/opts/src/procurements-prototype.tsx`, `procurements.css`, `procurements-data.ts`, `procurements.stories.tsx`; latest combobox focus correction included.
- App `ProcurementController`, Store/UpdateProcurementRequest, ProcurementBusinessRules and existing resource routes.
- DashboardLayout, dashboard.css and dashboard-motion.css supply the approved shared shell.
- DashboardService and `types/dashboard.ts` supply actual document URLs, current office, workflow route and next action.
- EnsureSsoEligibility owns directory synchronization. Form options use local Office.id for active SSO-mapped offices; labels abbreviations and hidden search keywords full names.
- ProfileController selected-year endpoint persists the user's year; year selection belongs in Settings only.

## Accepted behaviors

1. Server search (purpose, ID, office name/abbreviation, document reference), filters, status counts including Cancelled, sort, page size and pagination. Preserve existing created_at selected-year scope.
2. Any non-action row area selects the adjacent preview. Narrow screens and arrow actions open centered quick-view dialog. Preview opens full detail. Column controls persist per user.
3. Create/edit dialogs match prototype fields and show server validation/processing. Existing linked records lock office and particular. Direct create/edit URLs render the same dialogs.
4. Full detail shows actual linked documents, workflow route overview, full-name activity timeline, filtering, complete expandable status history and eligible document links/actions.
5. Archive uses accessible confirmation and existing soft delete. Linked transactions remain available.
6. Viewers have no mutation controls and direct mutation endpoints remain denied. Dependency rules remain PR existence before PO and PO existence before voucher, never predecessor completion.
7. Route-aware shared navigation, collapse persistence, mobile drawer, notification dropdown, account/settings/help, skip links, real names/roles/office and selected-year Settings.
8. Desktop/tablet/phone parity; wrapping, centered modal motion, focus restoration, clean combobox focus and reduced motion. Meaningful loading/error recovery.

## Tasks and ownership

### 1. Backend presentation contract

Owner: backend worker. Files: ProcurementController, optional single presentation service, ProcurementControllerTest/additional procurement contract tests. Preserve model and authorization rules. Explicitly serialize documents with DashboardDocument-compatible fields; use eager loading rather than a query per register row. Index keeps paginator/filters/options/can and adds formOptions/statusCounts. Show keeps current props and adds documents/formOptions, complete status history. Direct create/edit may reuse Index/Show responses with initialModal. Store/update may redirect to detail to match approved save flow; update existing assertions accordingly. Do not touch shell/frontend files.

VALIDATE: PHP syntax, focused procurement tests. Required behavioral tests: real document links/routes, search/reference/abbreviation, global status counts, sorting/pagination/year, active SSO form options, historical references, viewer denial, locking and archive.

### 2. Shared shell and Settings

Independent of task 1. Owner: shell worker. Files: DashboardLayout, AuthenticatedLayout, Profile/Edit, Dashboard.tsx year picker only, shared global CSS imports/theme and obsolete navigation helpers. Preserve prior Dashboard work. Generalize shell props for active navigation, title/breadcrumb and content root. AuthenticatedLayout becomes compatibility wrapper for existing page headers/children. Put selected-year control in Settings using existing route and remove Dashboard year selector. Remove unused old navigation components only after verifying references. Do not edit Procurements pages/CSS/types or PHP controller.

VALIDATE: npm build/typecheck. Check Dashboard and unrelated page direct entry, role-aware nav, collapsed/mobile nav and Settings persistence in integration pass.

### 3. Procurements UI

Owner: parent. Files: Procurements pages, domain components/types, new procurements.css. Reuse prototype markup/CSS and official installed primitives; adapt Tailwind4 attributes/colors to Tailwind3 HSL tokens without replacing primitive accessibility. Server-driven list state and real form submissions through Inertia. Preserve filter context when opening/returning from detail; retain all approved modal and focus behavior. Remove obsolete procurement backup experiments.

VALIDATE: npm build, Browser flow checks against exact built local container.

### 4. Storybook reference sync

Owner: parent. Preserve approved reference; record source hashes and fixed combobox style. Add explicit focused combobox stories if needed for enduring review, update reference sync and publish only OPTS catalog. No unrelated catalog publication.

VALIDATE: Hub pnpm typecheck / pnpm build:all, published Storybook focused state and exact served hash.

### 5. Integration, validation and review

Run composer test (in-memory SQLite), npm run build, scoped PHP Pint and git diff --check. Rebuild only local opts2026 container and verify health. Browser primary E2E: real register/detail, filters/sort/pagination, selections, all modals, search comboboxes, validation/save/edit/archive on clearly labeled local QA records, document URLs and timeline, Settings, Dashboard regression, responsive dimensions, keyboard/focus/reduced-motion source. Preserve existing SSO test accounts; no new access grants needed.

Review full scoped diff with PIV review; fix concrete findings and rerun affected gates. Save source sync, parity checklist, proof screenshots, implementation report and remaining limitations. No completion claim while a required flow or gate remains unresolved.

## Risks and boundaries

- Hub uses Tailwind4 while app uses Tailwind3; adapt data-slot attributes, tokens and transforms explicitly.
- Existing unrelated dirty Dashboard changes are prior authorized work, not to be reverted.
- Existing transaction creation/processing pages keep their server-backed functionality under the new shared shell; their page-specific redesign is separate from procurement detail.
- Legacy ETTS imports remain retired. Do not seed, drop or import data.
- Local container has immutable source copy; rebuild/copy verified source through normal Docker build, preserving storage and sessions.

## Completion

- [x] Backend contract and regression checks
- [x] Shared shell/theme and Settings
- [x] Complete Procurements interactions with real data
- [x] Storybook focused combobox reference synced
- [x] Local deployment, full gates and Browser parity checks
- [x] Review and delivery report
