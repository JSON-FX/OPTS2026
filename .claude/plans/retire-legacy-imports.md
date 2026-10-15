# Retire legacy imports and resolve remaining test failures

## Intent

The user has decided that OPTS will no longer support old database structures or legacy imports. Remove the ETTS upload, mapping, import, reporting, rollback, and clearing functionality. Retain the current procurement model and support its schema through tests. Resolve the remaining timestamp-ordering defect in current transaction activity.

## Decisions and boundaries

No open product questions remain. This is removal of a discontinued feature. Existing Laravel schema migrations remain as schema history; no data or tables are dropped. Existing procurement data remains accessible. Import audit tables can remain inert. No new import mechanism, dependencies, commit, push, or production deployment.

The checkout already contains uncommitted SSO integration and earlier MVP work. Preserve it. Continue on codex/sso-directory-integration; isolate this task by file ownership and a report, without stashing others' work.

## Context

- routes/web.php and routes/channels.php: import endpoints and broadcast channel.
- app/{Services,Jobs,Events}/Migration, Admin/MigrationController, MigrationImport/Record, BackfillMigrationCurrentOffice: isolated feature runtime.
- resources/js/Pages/Admin/Migration and AuthenticatedLayout: feature pages and desktop/mobile navigation.
- resources/js/types/models.ts: import-only types and obsolete flags.
- tests/Feature/Migrations/TypeSpecificTablesTest.php and tests/Support/MigrationTestHelper.php: outdated column expectations and fixture methods.
- app/Models/Transaction.php latestAction: max-ID default disagrees with chronological action history. DashboardService eagerly loads it for ETA/stagnation.
- tests/Feature/Models/TransactionActionsIntegrationTest.php: existing regression for out-of-order timestamps.

## Tasks

1. Remove the import backend, route registrations, broadcast channel, and import-only tests, including migration event privacy tests. Validate route generation and remaining notification tests.
2. Remove import pages, desktop/mobile menu links, import types, and import-only model mass-assignment flags. Mark historical import plan as retired; document supported current source and scope in README/CLAUDE. Validate source scan and TypeScript.
3. Rewrite current schema checks using supported model factories and actual PR/PO/voucher columns. Preserve unique transaction and restrictive foreign-key checks. Remove obsolete fixture helpers with no remaining callers. Validate schema tests.
4. Order latestAction by created_at, breaking timestamp ties by highest ID through Eloquent ofMany. Test lazy/eager loading and tied timestamps. Validate transaction action and ETA/dashboard tests.
5. Run full PHPUnit, TypeScript/build, scoped Pint, diff checks, and technical review. Record results and any deliberate deviations.
6. Rebuild local Docker and use Browser/Computer to verify admin navigation has no import feature, former import URLs return404, dashboard and completed procurement #3 still load. No new procurement needed for this removal.

## Acceptance

- No active ETTS import endpoints, channels, commands, jobs, models, services, screens, or navigation remain.
- Current schema tests enforce the current application model; old columns are no longer expected.
- Latest activity selects by timestamp and behaves identically when eagerly loaded.
- Full current test suite passes.
- Local app is healthy and existing completed MVP records remain visible.

## Validation

php -d memory_limit=512M vendor/bin/phpunit
npm run build
vendor/bin/pint --test (changed surviving PHP files)
git diff --check
php artisan route:list --json and command inventory
Browser/Computer smoke on local Docker rebuild

## Verified scope adjustment

Updating current schema tests exposed a missing PR transaction_id unique index. The original migration called unique() on a foreign-key definition rather than creating a unique index. Confirmed on local MySQL and SQLite tests; local duplicate count is zero. Add a new upgrade migration that creates the unique index when missing, checks existing duplicates without deleting them, and only rolls back its own named index. This enforces the user's existing duplicate-prevention requirement. Old import schema history is unchanged.

## Completion

All tasks completed. Full current suite passed: 687 tests, 2775 assertions. Build, scoped Pint, MySQL upgrade reversal/reapply, local deployment, and Browser smoke passed. See .claude/reports/retire-legacy-imports-report.md.
