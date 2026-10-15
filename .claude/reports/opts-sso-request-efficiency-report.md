# Implementation report — OPTS SSO request efficiency

Plan: `.claude/plans/opts-sso-request-efficiency.md`
Branch: `codex/opts-sso-request-efficiency`
Status: source implementation complete; CI, immutable image and deployment acceptance pending.

## Changes

- Added exact-request/token/upstream/client-bound successful authorization reuse. Browser eligibility and launch now share their one fresh response; new requests and replacement tokens remain fresh.
- Added shared failure-only cooldown and bounded numeric/HTTP-date Retry-After. Temporary failures fail closed and recover through fresh checks; no successful grants or tokens enter the shared cache. Denied employees never start a client-wide cooldown.
- Preserved retry headers through 503 eligibility, admin-directory, launch and introspection responses. Active:false, no-store, UUID checks and durable revocation remain intact.
- Reused the existing `cd8982b` stateless introspection route/test correction. Signed requests create no browser sessions/cookies. Browser launch retains CSRF and host authentication.
- Preserved current directory identity, role/office and 60-second metadata/fresh-write rules. No schema, migration, dependency, runtime configuration, limit or loader change.

## Validation

- PHP 8.4.26 / PHPUnit 11.5.43 full suite: 858 tests, 5,321 assertions passed. PHPUnit reports 37 PHPUnit deprecations; no test failures/errors.
- Focused SSO/host/directory suite: 120 tests, 1,084 assertions passed.
- Full Pint: 270 files passed. Changed-file formatting also passed.
- Widget frontend tests: 7 passed. PHP amount formatting: 12 checks passed.
- TypeScript and Vite production build passed. Existing browser-data/font-resolution build warnings remain; no dependency updates were introduced.
- MIS delivery/isolation and pinned-loader/default-disabled checks passed.
- `git diff --check` passed.
- Independent full feature-source/test review found no actionable issues. The additional ProfileController edit was checked as formatting-only and followed by the full suite.

## Important regressions covered

Actual middleware launch sends one authorize call; the next inbound request detects grant revocation. Request/token/client isolation, denial separation, shared cooldown across transient service instances, client isolation, countdown expiry, HTTP-date/numeric/invalid bounds, no automatic retry, and fresh post-cooldown denial are exercised. Signed success/replay/invalid/outage responses preserve database sessions and return no cookies. Temporary unavailability preserves the host login and does not permanently revoke a valid binding. Office/role changes continue to enforce business rules.

## Deviations

Full Pint exposed one existing concat-spacing issue in ProfileController; the single formatting-only line was corrected to pass the complete gate. The earlier unrelated activation review was preserved in the private operational receipts directory rather than included in this feature commit. No other source scope was added.

## Operational limits and release

No production or staging operations were performed during implementation. The widget and production host/key remain disabled. This does not certify 300–500 users or a measured reduction in production traffic. Actual database/Redis concurrency, candidate Browser acceptance and production retest are not claimed by these fake-HTTP/in-memory tests.

The SSO coordinator received the exact scope and checks. A newer direct human production-deployment request authorizes release preparation; production operations remain serialized by that coordinator. Required CI/image provenance, fresh backup/off-server restore, compatible-image review and release readback must finish before promotion. There are no new migrations; the existing additive revocation table must remain intact on rollback. Reusing the previous image must not remove business records or durable revocations.

Directory forced/CLI writer coordination remains a separate follow-up. The existing per-request directory identity call cannot be removed without an agreed fresh identity contract. No cross-request positive authorization cache was added.
