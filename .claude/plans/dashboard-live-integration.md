# Feature: Live Casebook Dashboard

## Feature Description and User Story
As an eligible OPTS user, I want the approved Dashboard connected to real procurement records so I can inspect office work and follow existing permitted actions.

Enhancement, medium complexity. Inherit Laravel 12, React 18, Inertia 2, Tailwind 3, existing new-york shadcn primitives, SSO-only roles, and current workflow state machine. Source: Storybook projects/opts/src/dashboard-prototype.tsx and catalog.css (latest approved controls revision, 2026-10-14). No framework upgrade.

## Scope
Dashboard only: role-aware collapsible navigation, account footer, live notifications, year/office scope, totals, expandable attention, workload/turnaround and activity before paginated document register/detail, volume and incidents, configured notices. Existing document pages and processing endpoints enforce all actions. No workflow, SSO grant, office mapping, import, or transaction-state changes. No new announcement CMS; configured notices are hidden by default and refreshed with Dashboard polling. No synthetic data in the app. No commit/push requested.

## Context References
- resources/js/Pages/Dashboard.tsx:116 — current polling, settings, workload.
- resources/js/Layouts/AuthenticatedLayout.tsx:40 — shared props, role navigation, year update, flash.
- resources/js/Components/NotificationBell.tsx:35 — Reverb subscription/read APIs.
- app/Http/Controllers/DashboardController.php:24 — year and current props.
- app/Services/DashboardService.php:28,90,160,220 — active transactions, workload, activity, attention.
- app/Services/EtaCalculationService.php — authoritative business-day/idle/ETA calculations.
- tests/Feature/DashboardTest.php; tests/Unit/Services/DashboardServiceTest.php — SQLite/PHPUnit patterns.
- docs/design/dashboard-direction.md; Storybook projects/opts/DESIGN.md — approved layout/tokens.
- Official registry https://ui.shadcn.com/docs/components/popover and /sheet — existing compatible primitives; MCP registry/examples inspected this turn.

## Implementation Plan / Atomic Tasks
1. UPDATE DashboardService, DashboardController, config/opts.php and focused PHPUnit tests. Add validated office/category/search/page filters; scope totals, workload, activity, attention, performance consistently by year and current office (activity uses participating offices). Procurements in office scope are associated with transactions currently there. Fix empty loaded-set leakage and performance upper year bound. Add paginated documents with full office route from eager-loaded workflow (route preview, actual history remains on document page). Attention retains existing ETA rules; expose target, idle reason and untruncated matching count. Provide configured scheduled notices with no default records. VALIDATE: PHP syntax and targeted Dashboard tests. SATISFIES AC2,3,4.
2. CREATE DashboardLayout.tsx using installed shadcn Sheet/DropdownMenu/Avatar/Button and existing NotificationBell. Dashboard-only shell, correct role guards, account/footer, mobile Sheet, persistent collapse, flash/toast. UPDATE NotificationBell accessible unread label and safe record navigation after successful mark-read. VALIDATE: npx tsc --noEmit. SATISFIES AC1,4.
3. UPDATE Dashboard.tsx and CREATE scoped Dashboard CSS/types; reproduce Casebook geometry and colors with compatible app primitives, local Source Sans 3 assets, no catalog toolbar. Inertia server filters/pagination and polling retain scope. Show original records/URLs, expandable attention search, full-name office route preview, existing document link, role-specific text. Preserve user preferences. Configure dismissible notices except emergency. VALIDATE: npm run build. SATISFIES AC1,2,3,4.
4. UPDATE Storybook approved reference metadata/source snapshot and app sync record; build OPTS catalog and verify served latest prototype. Preserve unrelated Hub files. VALIDATE: pnpm --filter @opts/design-system typecheck and build-storybook. SATISFIES AC5.
5. VALIDATE full PHPUnit, frontend build, PHP formatting; Browser desktop/mobile/roles/filters/search/pagination/sidebar/notifications/real document link. Review full scoped diff, fix findings, record report. Rebuild/recreate only OPTS local image if needed to expose app changes. SATISFIES AC1–5.

## Acceptance Criteria
- AC1 Approved Casebook layout/controls at desktop and phone, no overflow; workload/activity above register; account footer and collapsed sidebar.
- AC2 Real records and counts, selected year/office applied consistently; server pagination/search/category, accurate route context and existing record URLs.
- AC3 Attention calculated by existing backend business-day rules, multiple records inspectable, no fictitious notices/metrics.
- AC4 SSO mapping and all workflow authorization preserved; Viewer cannot process; role navigation/pending counts correct; notifications retain Reverb and read ownership.
- AC5 Latest prototype saved and viewable in Storybook; explicit source sync, verification/report and limitations recorded.

## Testing Strategy / Commands
Use existing PHPUnit SQLite suite and regression tests for empty year scope, office isolation, soft deletion, route detail, attention targets, configured notice scheduling, and Viewer dashboard/access. Use existing host PHP if present; otherwise installed local PHP runtime or development container with test dependencies, never migrate real database for tests. Commands: `npm run build`, `vendor/bin/pint --test --dirty`, `php artisan test`, focused `php artisan test --filter=Dashboard`. Browser/Computer primary for E2E at opts.lgu.lan. Verify local Docker source/build parity before claiming live integration. No blind seeders.

## Open Questions / Assumptions
Nothing open — user approved the latest prototype and requested live integration with preserved roles/workflows. Announcements have no existing publishing backend; use deployment-configured notices, hidden when empty. An in-app publishing editor is separate scope. Office-scoped procurements are those with a document currently at that office; display this definition. Route preview describes assigned workflow; actual endorsement timestamps/history remain on the document page.

## Completion Checklist
- [x] Backend contract and focused regressions
- [x] Live UI and notification/shell integration
- [x] Storybook reference/source sync
- [x] Required validation and visual review
- [x] Implementation report

## Amendments

- 2026-10-14 — Owner explicitly required complete prototype interaction parity. Added shared queue/pending/help/document dialogs, notification/activity/attention overview handoffs, live search, global sorting, exact checkbox customization, keyboard office tooltips and policy-derived next-action links. Prototype toolbar remains catalog-only. Live route/receipt semantics remain authoritative.
- 2026-10-14 — Technical review found missing attention metadata outside register scope, stale pending receipts after notifications, missing-record fallback, and handoff office labeling. Fix with existing ETA/policy services and actual workflow office IDs; no workflow mutations.
- 2026-10-14 — Full validation exposed random duplicate abbreviations in OfficeFactory, including an existing TransactionAction model test. Make generated abbreviations unique; test-only one-word change honors the database uniqueness rule.

- 2026-10-14 — Owner clarified workload numbers open an isolated modal. Queue office/category/search/sort/page now have independent request props; Dashboard totals, scope and register remain unchanged. Long purposes wrap in both tables. Scoped CSS adds short modal/backdrop/Sheet/menu/sidebar motion and reduced-motion overrides; exit content is retained. Review fixes prevent selectable stale queue rows and background polls from replacing queue payloads.
