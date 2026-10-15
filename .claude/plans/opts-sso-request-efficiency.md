# OPTS SSO request efficiency

## Goal and authorization

As an authorized employee, I want OPTS to avoid redundant SSO work and respect temporary upstream failures without weakening current role, office or grant enforcement.

The human request in LGU Connect turn `01a13e5d-dfe0-7023-b90e-95dc4aa54e90` explicitly authorizes coordinated local fixes. Production changes, deployments, restarts, live limit changes, staging startup and widget activation remain excluded. Staff are registering in production SSO. This patch does not access production.

Baseline is OPTS `5a5eb48`. Reuse the existing owned release worktree; preserve the dirty original checkout and the prior untracked activation review. Create a local `codex/opts-sso-request-efficiency` branch. Do not commit or publish this work without a separate request.

## Findings and inherited contracts

- `EnsureSsoEligibility::handle` authorizes the browser token; `ConnectPilotHost::launch` authorizes the same token again. `SSOService` is transient, so instance-only caching would miss the duplicate.
- Introspection already performs one fresh SSO authorization per signed request. It must never reuse authorization across inbound requests or read browser cookies.
- `SSOIdentityService::synchronize` separately validates current employee directory identity, role and assigned office. Preserve that lookup; the existing authorization payload is not a replacement.
- `SSOOfficeService::refreshIfStale` already has a 60-second metadata marker and refresh lock. Business/workflow writes force a current full snapshot. Preserve both freshness rules and stable local/SSO office IDs.
- Signed launch/introspection are one-use protocols. Do not automatically replay them, exchange codes or handoffs.
- Upstream Retry-After is currently only logged, then discarded when failures become503.
- Existing clean commit `cd8982b` removes the entire web middleware group from introspection and tests session/cookie isolation. Reuse its route/test patch without changing its separate worktree.

## Scope

1. Add explicit request-bound authorization reuse keyed by exact token, upstream and client credentials. Only successful authorization payloads can be reused. Keep plain `authorize()` fresh for callers without request context.
2. Preserve bounded Retry-After on authorization/directory failures. Support numeric and HTTP-date values, normalize to 1–300 seconds, and use 2 seconds when absent/invalid. No automatic upstream retries.
3. Add a shared negative cooldown for temporary authorization/directory failures, keyed by upstream/client. It stores only an expiry timestamp, never tokens or successful grants. Requests during cooldown fail closed with 503 and remaining Retry-After;401/403 and explicit denials never start a client-wide cooldown.
4. Propagate sanitized retry headers through browser eligibility, widget launch and signed introspection. Success response shapes, denial/revocation semantics and no-store protections stay intact.
5. Apply the existing stateless route/tests from `cd8982b` locally.

Directory locking across forced/CLI writers and scheduler coalescing remain documented follow-ups. Do not silently relax the fresh-write snapshot contract. This patch does not claim 300–500-user capacity or remove necessary directory eligibility reads.

## References

- `app/Services/SSOService.php`: current authorization/directory HTTP boundaries and sanitized logging.
- `app/Http/Middleware/EnsureSsoEligibility.php`: grant/office enforcement and temporary-unavailability handling.
- `app/Services/ConnectPilotHost.php`: exact-token grant checks, signed requests and durable revocation.
- `app/Http/Controllers/ConnectPilotController.php`: no-store JSON error mapping.
- `app/Exceptions/SSOUnavailableException.php`: add backward-compatible retry metadata.
- `routes/connect.php`, `tests/Feature/ConnectPilotTest.php`: stateless correction and middleware-enabled coverage.
- `tests/Unit/Services/SSOServiceTest.php`, `tests/Feature/Auth/SSOAuthenticationTest.php`, `tests/Feature/SSOOfficeSyncTest.php`: existing test patterns.
- [Laravel HTTP client timeouts](https://laravel.com/framework/docs/12.x/http-client#timeout): retain bounded timeouts and avoid implicit retries.
- [Laravel cache](https://laravel.com/framework/docs/12.x/cache): existing shared cache and TTL semantics.
- [RFC9110 Retry-After](https://www.rfc-editor.org/rfc/rfc9110.html#section-10.2.3): delay-seconds and HTTP-date behavior.

## Ordered implementation

### 1. Request reuse

Add `authorizeForRequest(Request $request, string $token)` as an explicit wrapper. Store the successful result in server-side request attributes keyed by a digest of the token/upstream/client context. Use it in eligibility and host checks. Never put authorization or bearer values in session/cache/logs/browser props. Preserve current UUID comparisons in both callers.

Validate: request identity/token separation tests and middleware-enabled launch count regression.

### 2. Failure controls

Extend the existing exception constructor without breaking message/code/previous callers. Add safe retry headers. Parse and bound upstream Retry-After; apply a negative-only TTL in the existing cache. Preserve a cooldown set by another concurrent failure; never clear it merely because an in-flight request succeeds. Map known temporary failures with those headers while retaining generic messages. Keep strict denials separate from unavailability.

Validate: numeric/date/malformed header bounds, failure cooldown across service instances, expiry/recovery, client isolation, no cooldown from401/403, no automatic POST replay and no credential leakage.

### 3. Stateless integration

Apply only the route/test delta from existing `cd8982b`. Add middleware-enabled launch, cross-request revocation and Retry-After integration tests. Update existing immediate-outage recovery expectations to respect the newly returned delay. Verify database sessions/cookies remain unchanged on successful, denied, replayed and unavailable introspection.

### 4. Validation and review

Use local PHP with PHPUnit's isolated in-memory SQLite and fake HTTP only. Do not run seeders, migrate a real database, use the live SSO service or start staging. Required commands:

```sh
php -d memory_limit=512M vendor/bin/phpunit --colors=never tests/Unit/Services/SSOServiceTest.php tests/Feature/ConnectPilotTest.php tests/Feature/Auth/SSOAuthenticationTest.php tests/Feature/SSOOfficeSyncTest.php
php -d memory_limit=512M vendor/bin/phpunit --colors=never
vendor/bin/pint --test
node --test tests/frontend/connect-widget.test.cjs
python3 scripts/check-mis-deployment.py
git diff --check
```

Run PIV validation and independent review. Fix findings and repeat affected gates. Report source-only verification separately from missing deployment/browser/load acceptance. Send the contract/result to Connect and SSO owners; keep production disabled.

## Acceptance

- One authorization HTTP request for a middleware-enabled launch; a new inbound request or changed token gets a fresh check.
- Successful grant reuse never crosses requests or credential scopes.
- Temporary failures return503/no-store with bounded numeric Retry-After, preserve host login, and do not revoke a valid binding.
- Client-wide cooldown prevents repeated upstream calls during its bounded lifetime, with no cached permission fallback.
- Denial, UUID mismatch, logout, office/role changes and one-use replay retain current enforcement.
- Introspection creates no browser session or cookies; launch retains authentication/CSRF.
- Focused/full local tests and independent review pass. Production, widget, host/key, limits and staging remain unchanged.

## Open questions and confidence

No user decision is open: existing contracts and both owners' acknowledgments settle the failure shape and security boundary. Default failure cooldown is 2 seconds; parsed delays are capped at300 seconds. These are conservative failure controls, not increased rate budgets or successful-authorization TTLs. Shared cooldown requires the already configured shared cache and adds a cache read to guarded upstream calls. Backend/host capacity remains unverified. Complexity medium; confidence 8/10 before tests.

## Amendments

- 2026-10-15: The human explicitly requested production deployment in SSO Portal user message `01a13e69-7276-77b0-8258-e42b7b6f2916` after the OPTS status. Commit/PR/CI preparation is now authorized; the SSO owner serializes production operations and must provide the release slot. Staging and widget/host/key remain disabled; no live limit changes.
- 2026-10-15: Full-repository Pint found one existing concat-spacing issue in ProfileController. Applied only its formatting correction so the complete 270-file gate passes. No profile behavior changed. Full backend tests were repeated afterward.
