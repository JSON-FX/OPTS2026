# Implementation Report — LC-029 OPTS Connect pilot adapter

**Plan:** `.claude/plans/lc-029-opts-connect-pilot.md`  
**Branch:** `feature/lc-029-opts-connect-pilot`  
**Status:** PARTIAL

## Summary

The OPTS host can issue a signed Connect launch after a fresh SSO application grant, answer signed Connect introspection, and send signed revocation on logout or account switch. A pinned local LC-027 loader mounts the widget on authenticated Inertia pages. The pilot defaults off, rejects production, and requires exact HTTPS origins, encrypted database sessions, Secure and HTTP-only cookies, shared cache, and a server-held host secret.

## Tasks completed

- Added pilot settings and disabled example values: `config/connect_pilot.php`, `.env.example`.
- Added host launch, introspection, nonce replay defense, binding checks, and signed revocation: `app/Services/ConnectPilotHost.php`, `app/Http/Controllers/ConnectPilotController.php`, `routes/web.php`.
- Distinguished confirmed SSO denial from an outage and persisted a permanent binding revocation digest: `app/Services/SSOService.php`, `database/migrations/2026_10_09_000000_create_connect_pilot_revocations_table.php`.
- Integrated account-switch and logout revocation: `app/Http/Controllers/Auth/SSOController.php`.
- Exposed only the public origin and same-origin launch URL to authenticated pages: `app/Http/Middleware/HandleInertiaRequests.php`.
- Mounted the pinned loader from the authenticated layout: `resources/js/Components/ConnectPilotWidget.tsx`, `resources/js/Layouts/AuthenticatedLayout.tsx`, `resources/js/types/index.d.ts`, `resources/views/app.blade.php`, `public/js/lgu-connect-widget.js`.
- Pinned loader SHA-256: `0a9ddcdf1dcda9a46c66712e91b5443678ddb1c5911d78038bc9b51366f53c81`.

## Tests added

`tests/Feature/ConnectPilotTest.php` covers the disabled and unsafe configuration gate, public-only loader props, fresh matching SSO grant, signed launch, forged and replayed introspection, inactive or changed local users, removed host session, grant denial and outage, signed logout revocation, and Connect outage. It proves an old binding stays revoked after the SSO grant returns, even if a stale cache value reappears. It also proves a transient SSO outage preserves the binding and OPTS sign-in. All cases use isolated SQLite and synthetic HTTP responses.

## Validation results

- `php artisan test tests/Feature/ConnectPilotTest.php --no-ansi`: 10 tests, 130 assertions passed; PHP 8.5 deprecation notices remain.
- `npm run build`: TypeScript and Vite passed.
- Scoped `vendor/bin/pint --test ...`: changed PHP files passed.
- `git diff --check`: passed.
- Full `APP_KEY=abcdefghijklmnopqrstuvwxyz123456 php artisan test --no-ansi`: 42 failures on this branch and the same 42 failing test names at clean base `17650e3`. The four `SSOAuthenticationTest` callback fixture failures are identical at base. Its authorize fakes omit `employee.uuid`, which the existing callback requires. One earlier run hit a random `offices.abbreviation` factory collision; that test passed alone, and the final full run matched the base failures.
- Full `vendor/bin/pint --test --no-ansi`: 15 style failures, all in untouched files.

## Deviations from plan

The maintained OPTS checkout still has another session's uncommitted SSO code-exchange work. The adapter was implemented against committed `17650e3` without copying those edits. Rebase and callback acceptance remain pending. The adapter requires encrypted database sessions, because the current session stores the SSO bearer.

## Issues and remaining gates

The committed callback still accepts a bearer in a query parameter. Do not enable this pilot until the maintained code-exchange changes are committed, rebased here, and retested. The existing `.env.example` includes `/api/v1` in `SSO_API_URL`, while the current `SSOService` appends `/api/v1` to its requests; reconcile that setting during the SSO rebase. Apply the new revocation migration in isolated staging after review. Register exact OPTS and Connect staging origins, host key and secret, and SSO test identities before Browser acceptance. No operational data migration, staging deployment, real sign-in, or two-host acceptance was performed. No commit or push was made.
