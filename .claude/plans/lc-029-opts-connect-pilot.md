# LC-029 — OPTS 2026 Connect widget pilot

**Status:** Isolated adapter implemented. Secure callback integration and live two-host acceptance remain open.
**Complexity:** High; the SSO callback and session changes in the maintained OPTS checkout are currently uncommitted work in another session.
**Implements:** LC-029 in `lgu-communication/docs/lgu-connect.backlog.md`.
**Inherits:** Connect architecture §5 and §9, LC-024 host adapter contract, and LC-026 credential contract. OPTS uses Laravel 12 and Inertia.

## Outcome and boundary

As an employee signed into OPTS through LGU SSO, I can open Connect without granting the OPTS server access to chat content. OPTS attests to its own current SSO-backed session. Connect independently authenticates the employee and authorizes conversations.

The pilot is staging gated. The Connect loader is loaded only from OPTS's own page after launch. Host secrets and SSO bearers remain on the OPTS server. Production configuration remains disabled until the two-host acceptance is complete. No OPTS procurement permission is treated as a Connect conversation permission.

## Existing seams

- `app/Http/Controllers/Auth/SSOController.php:29-148` owns redirect, callback, session `sso_token`, and logout. It currently accepts a callback query token in committed `main`; the maintained checkout has uncommitted code-exchange changes. Integrate those changes first, without copying or committing another session's dirty files from this pilot branch.
- `app/Services/SSOService.php:21-125` owns SSO client headers, authorize, validate, and logout. The current `authorize` call must be used on every launch and introspection, with exact canonical employee UUID equality.
- `app/Models/User.php:24-59` carries `sso_uuid` and `is_active`; reject an inactive or changed local user.
- `routes/web.php:12-204` and `routes/auth.php:5-24` hold authenticated Inertia routes and sign-out. Add the launch route behind web authentication and CSRF. Add server-only introspection as an independent route with HMAC, nonce, and throttle checks.
- `bootstrap/app.php:12-42` registers the web route file. Use existing route registration instead of adding Sanctum or a public chat API.
- `tests/Feature/Auth/SSOAuthenticationTest.php` is the existing SSO test pattern; add a separate `tests/Feature/ConnectPilotTest.php` using synthetic SSO/Connect HTTP fakes and isolated test storage.
- The Assistance pilot adapter in its separate repository is a reference for LC-024 signature format. OPTS must verify its own SSO grant and local account; do not copy Assistance's local-user mapping rule.

## Server contract

Use staging-only `CONNECT_PILOT_ENABLED=false`, exact HTTPS Connect origin and exact HTTPS OPTS `APP_URL`, a stable registered OPTS host ID and key ID, and a dedicated 32-byte-or-stronger server-held signing secret. Require a database or Redis session driver, a shared cache for nonce/binding records, and a Secure host cookie. Fail closed if any are missing.

On `POST /connect/pilot/launch`, verify the current Laravel user is active, has one canonical lower-case `sso_uuid`, and has a server-held SSO bearer whose fresh `/sso/authorize` result has `authorized=true` and exactly that UUID. Bind a fresh 32-byte random reference to the current server session; persist the session before contacting Connect. Send the exact LC-024 signed `POST /api/widget/host-launches` request. Return only `launch_ref`, `expires_in`, and the public Connect embed URL to the OPTS browser. Never return the binding, key, or SSO bearer.

On signed `POST /connect/pilot/introspect`, check the exact raw-body HMAC, host/key headers, 30-second timestamp, fresh 70-second nonce, body schema, active binding, current server session and active user, same SSO UUID, and a fresh OPTS SSO application grant. Return LC-024's exact active response with `host_session_digest`; otherwise deny without leaking identity. For host logout or account switch, invalidate the binding and send LC-026's signed revocation event; local logout must complete even if Connect is unavailable.

The browser adapter uses LC-027's small loader and same-origin CSRF launch endpoint. It posts the launch reference to the Connect iframe by exact origin and source only. It does not copy the SSO bearer or connector secret into JavaScript. It preserves unrelated OPTS navigation and forms if Connect is unavailable.

## Ordered implementation

1. Rebase this isolated pilot branch onto the committed SSO code-exchange revision after the maintained checkout owner has finalized it. Review `SSOController`, `SSOService`, session configuration, and existing tests on the rebased commit. **Validate:** `git diff --check`, focused SSO authentication tests.
2. Add `config/connect_pilot.php` and empty example environment keys. Enforce exact HTTPS origins, enabled flag, secure session storage and cookie, key length, and shared cache before any launch. **Validate:** a disabled/misconfigured pilot denies without a Connect request.
3. Add OPTS host service/controller and launch/introspection routes. Reuse LC-024's exact signed-body format, canonical UUID/reference validation, nonce replay defense, bounded HTTP timeout, and no-store responses. Do not expose a bearer through logs or JSON. **Validate:** success and denial fixtures, forged/replayed signatures, stale/changed sessions, upstream failure.
4. Extend logout to revoke only the exact OPTS binding. Make remote notification best effort and local logout unconditional. **Validate:** logout during Connect outage, account switch, repeated logout, and unaffected web/Assistance session fixtures.
5. Integrate LC-027's public loader with a staging-only OPTS launcher. Use existing Inertia layout/navigation conventions and accessible focus restoration; do not alter procurement forms. **Validate:** Vite build and Browser keyboard/mobile, popup-blocked, and unrelated-form flows.
6. Run the repository test/lint/build gates with isolated test configuration. Review the diff and record synthetic evidence. Then register exact staging host origin, introspection URL, key, SSO client, and test identities through the operational process. **Validate:** live cookie-blocked sign-in, mismatch, host logout, grant removal, two-host transfer, and web continuity with exact revisions.

## Validation and acceptance

Use `php artisan test --filter=ConnectPilotTest`, `php artisan test --filter=SSOAuthenticationTest`, `./vendor/bin/pint --test`, `npm run build`, and `git diff --check`. Confirm test database/cache settings before invoking the suite; do not migrate OPTS operational data. Browser and Computer are the primary end-to-end tools.

The branch is code complete only after the adapter and checks pass. LC-029 is accepted only after the real registered OPTS staging host and Assistance staging host prove the same SSO employee UUID, durable conversations across both widgets and web, cookie-blocked handoff, host logout, denial, and unaffected host navigation. Synthetic fakes cannot close those checks.

## Risks and assumptions

- The committed OPTS `main` callback still reads a query token. The maintained checkout has uncommitted code-exchange edits. Do not take ownership of those edits or declare live SSO acceptance from this isolated branch.
- OPTS may have older environment examples using `.local`; use exact registered staging origins from LC-006 instead of guessing an origin.
- Session data must be readable during server-to-server introspection. A process-local `array` or file cache cannot satisfy the binding/nonce contract across workers.
- LC-026 host revocation request shape is defined in its plan. Recheck its shipped implementation before wiring the OPTS logout call.

No new owner choice is needed for this plan. The user selected OPTS as Host B and authorized a staging pilot. Exact staging registrations and identities remain operational inputs, not values to invent in code.

**One-pass confidence:** 7/10 after the SSO callback revision and LC-027 loader are integrated.

## Amendments

The maintained OPTS checkout still has uncommitted SSO code-exchange work owned by another session. To avoid copying that work, implementation steps 2 through 5 were completed against committed `17650e3` before step 1. The new adapter is disabled by default and refuses production. Rebase onto the committed code-exchange revision, rerun SSO and pilot tests, then complete live staging registration and browser acceptance. The pinned local LC-027 loader has SHA-256 `0a9ddcdf1dcda9a46c66712e91b5443678ddb1c5911d78038bc9b51366f53c81`.

The review found that a confirmed SSO grant denial must permanently invalidate the old binding. The adapter now writes a digest tombstone to `connect_pilot_revocations` before denying it. A later restored grant requires a new host reference. SSO network errors and 5xx responses deny current introspection with 503 while retaining the existing binding and the unrelated OPTS session.
