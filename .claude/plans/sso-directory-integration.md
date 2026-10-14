# SSO directory integration

## Intent and decisions

SSO owns operational offices, identities, assignments, and application grants. OPTS retains local foreign keys and historical actors for procurement history. Use the existing Laravel HTTP client, Eloquent models, Spatie roles, and Inertia UI. No new dependencies.

User confirmed SSO guest → Viewer, standard → Endorser, administrator/super_administrator → Administrator. Viewer sees dashboard and procurement/transaction details but cannot create, receive, endorse, complete, or configure the application. Endorser retains procurement management and office-matched processing. Administrator retains configuration powers. Job title never grants access. The Mayor demo uses guest.

Use an external SSO office ID alongside local office IDs. Initial unique exact abbreviation/name matches reconcile existing records; conflicting matches fail without partial updates. Unmatched historical offices remain stored but inactive. Missing MMO-PO/MMO-PSMD will be added in SSO later; do not invent aliases. Use clearly labeled test workflows with active SSO offices for repeat MVP testing.

Office metadata refreshes automatically on authenticated requests (at most a one-minute metadata interval) and on a scheduled command. New assignments validate against a freshly synchronized SSO office snapshot. Directory eligibility and role authorization are fetched live, never cached. Fail closed on unavailable eligibility. Logout remains available.

Local office and user editing is removed. Administrators see live SSO directory display fields; ordinary users see their own profile and business actor names. Directory search is server-side with server-controlled role filters. Keep private user fields out of business pages.

## References

- `app/Services/SSOService.php`: existing exchange, authorize, validate, logout.
- `app/Http/Controllers/Auth/SSOController.php`: login provisioning.
- `app/Services/EndorsementService.php`: office and role checks.
- `app/Models/Office.php`, `app/Models/User.php`: local historical foreign keys.
- `/Users/jsonse/Documents/development/lgu-dev/.worktrees/sso-reference-directory/docs/consumer-directory.md`: authoritative directory contract.

## Implementation tasks

1. Add SSO reference columns, strict directory HTTP methods, office synchronization service/command, and active assignment validation. Preserve all existing primary keys. Validate with sync tests covering pagination, rename, inactivity, mapping collisions, and failed partial fetches.
2. Implement fresh authenticated-request authorization, UUID-bound identity synchronization, explicit role mapping, callback updates, and safe notification eligibility. Validate with auth/role/office/revocation/outage tests.
3. Replace local admin identity/office CRUD with SSO read-only pages. Change every live office dropdown to abbreviation while leaving full-name timelines intact. Validate with controller tests and TypeScript/build checks.
4. Remove seeded operational offices/users from default bootstrapping. Preserve historical ETTS actors as inactive snapshots. Document role mapping, sync behavior, and missing-office handling.
5. Run focused PHPUnit, Pint, TypeScript, build, and required review. Diagnose existing stale controller/auth tests and update them to the actual request contract. Record unrelated suite failures accurately.
6. Back up local OPTS data, migrate/rebuild local container, run sync and inspect mappings. Verify automatic upstream updates without altering real user grants.
7. Browser/Computer MVP: SSO demo users, Viewer restrictions, abbreviated dropdowns, full-name timeline, one new procurement with sequential PR/PO/VCH, all office handoffs and receipts, duplicate reference rejection. Use active SSO offices in separate demo workflows.

## Acceptance criteria

- SSO office additions/renames/inactivity automatically appear locally; inactive/unmapped offices cannot receive new assignments.
- Existing procurement and action references remain intact.
- SSO role/office changes and revocation take effect during existing sessions; no stale-eligibility fallback.
- Mayor guest has read-only access, including direct mutation endpoints.
- Admin directory exposes only contract display fields and cannot edit SSO-owned data locally.
- Office dropdowns show abbreviations; timelines show full names.
- Repeat MVP and PR/PO/VCH duplicate checks pass.

## Validation

Use repository PHPUnit feature/unit tests; `vendor/bin/pint --test` on changed PHP, `npx tsc --noEmit`, `npm run build`, and `git diff --check`. UI validation uses Browser/Computer. Review security, historical identity preservation, pagination, and failure handling before deployment to the local container.

## Existing work and deviations

The checkout contained earlier local infrastructure and MVP fixes. Created a feature branch carrying these changes without stashing or committing them. Preserve unrelated edits; no commit/push requested. Historical inactive ETTS actor records remain permitted solely for audit attribution.

## Completion

Implemented and deployed to the local Docker container. Browser/Computer acceptance completed on procurement #3 with PR#3, PO#2 and voucher#2. All three workflows completed sequentially using separate office demo accounts. Each duplicate-create attempt was rejected before the unique record was created. Mayor guest was verified read-only, including a direct create URL returning403. The upstream synthetic office rename and inactivity propagated automatically; the test office remains inactive.

User selected a shared SSO consumer rate limit of100 requests/minute for local and deployment. Local SSO application was updated; the deployment setting is documented in docs/sso-directory-access.md. No production environment was accessed. Ordinary mapped-user read cost was reduced from3 to2 live identity requests without caching authorization.

See docs/sso-mvp-acceptance-2026-10-14.md for evidence, validation outcomes, and existing test limitations.
