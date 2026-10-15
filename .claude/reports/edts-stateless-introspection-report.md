# EDTS stateless Connect introspection

## Incident and root cause

The parent task reported intermittent two-widget timeouts during staging acceptance. Independent read-only sampling found EDTS `insert:sessions` waiting for handler commit in three samples, alongside cache writes and separate SSO audit-log writes. A successful host introspection took 2.484 seconds. The sampling also found storage I/O pressure and a separate SSO restart interruption, so this correction does not claim to resolve every latency source.

The introspection route inherited Laravel's `web` middleware group. It excluded browser SSO eligibility and CSRF validation but retained StartSession. Each real server-to-server request carries no browser session cookie, so StartSession creates and commits an unrelated anonymous database session and emits a browser cookie. The host service already verifies the actual persisted browser session directly through its binding record, database row, encrypted payload, current token, identity, expiry, and live SSO authorization.

Reproduction is confirmed: resetting the test's in-memory session state to model a separate cookie-free server request caused the unchanged route to increase the sessions table from one row to two. The regression failed before the fix.

## Focused fix

Exclude the `web` middleware group from only `/connect/pilot/introspect`. Laravel resolves the excluded group into its middleware classes. This also removes session-dependent view/Inertia middleware, preventing errors caused by excluding StartSession alone. The route-specific source throttle remains. The browser launch route keeps its web middleware, authentication, CSRF validation, and session persistence.

No changes were made to HMAC signatures, nonces, replay prevention, per-binding throttling, sessionToken validation, revocation, SSO grants, caching policy, or database durability. There is no new dependency.

## Validation

- Regression covers three independently signed successful requests, replay denials, and invalid-signature denial. Session row count and contents remain unchanged; responses set no cookies.
- Initial browser launch still persists a host session and sets its session cookie. Missing browser CSRF input returns 419 with no upstream request.
- Existing host suite covers revoked grants, logout, inactive/mismatched identities, missing/expired/tampered encrypted host sessions, current token use, outage recovery, and binding/source configuration.
- Focused host suite passed: **21 tests, 639 assertions** using PHP 8.4 and isolated SQLite. Pint passed for both changed PHP files; whitespace checks passed. `artisan route:list --path=connect/pilot -vv` confirmed introspection has only its source throttle and launch retains its full browser middleware stack. Full PHP suite also passed: **712 tests, 3433 assertions**, with 37 existing PHPUnit deprecations. The pull request repeats validation and builds/tests the image.

## Remaining acceptance

After the exact image is deployed, repeat two-widget messaging and inspect database/session-write activity and request timing. Remaining cache/nonce/audit writes are intentional and unchanged. Office storage latency, SSO restarts, and concurrent-load capacity need separate evidence. This implementation subtask makes no MIS runtime changes.
