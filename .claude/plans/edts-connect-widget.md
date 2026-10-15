# EDTS 2027 Connect widget

## Scope and decisions

Implement in `opts2026` (EDTS 2027), locally first at `https://opts.lgu.lan` with local Connect at `https://connect.lgu.lan`. Prepare configuration and release instructions for staging and production; do not deploy remotely. User settled this October 15. Inherit LC-024/025/026/027 host and widget contracts from LGU Connect. Reuse the existing LC-029 adapter and pinned loader, updating it for committed SSO code exchange at bf1b98b. No new dependencies or account/password system.

Work on `feature/edts-connect-widget` in an isolated worktree. The primary checkout contains substantial uncommitted EDTS UI/domain work from another session. Apply only the reviewed widget additions and small integration patches to the primary checkout for local testing. Never replace, stage, or commit unrelated files.

## Implementation

1. Backend: port ConnectPilotHost, controller, config, revocation migration and focused tests. Reuse current SSOService::authorize; validate active user, canonical employee UUID and current grant. Verify signed introspection, nonces, expiry and exact persisted session binding. Revoke before logout, confirmed access denial and successful account replacement. Keep temporary SSO failures distinct from confirmed denial. Default disabled in all environments; production requires explicit valid configuration. Validation: focused ConnectPilotTest and SSOAuthenticationTest with SQLite memory; Pint on changed files.
2. Frontend: share only public origin, launch URL and identity when configured/authenticated. Mount the pinned loader once beside Inertia's application root. Observe navigation to remove/recreate it on identity/config changes, preserving the iframe for ordinary navigation. CSRF-protected same-origin POST launches; no secrets or bearers enter JavaScript. Validate TypeScript/Vite and browser navigation/draft behavior.
3. Local runtime: repair local Connect ingress configuration mismatch, verify local SSO authorities, provision dedicated local host signing key privately and exact host origins. Use encrypted database sessions and shared cache. Apply only the new revocation migration; never seed operational databases. Enable locally only after tests. Rebuild local EDTS and Connect as needed. Validate trusted HTTPS and host-to-Connect/introspection reachability.
4. Acceptance: Browser first, Computer as needed; isolate two synthetic authorized local accounts when available. Verify launch, portal handoff, identity matching, messaging/unread/seen, minimize/reopen, navigation persistence, logout invalidation and revoked access denial. Use Playwright only for gaps in primary tools. Record local vs simulated results and blockers precisely.
5. Release preparation: document env matrix, exact origins/SSO authority separation, private secret provisioning, CSP frame ancestors, rollback, health checks, and explicit staging acceptance before production. Leave staging/production disabled and exact EDTS remote hostnames unresolved until supplied. Capture reviewed loader checksum and test/source evidence.

## Required references

- Existing LC-029 plan, ConnectPilotHost and tests in /Users/jsonse/Documents/development/opts2026-lc-029-pilot.
- Current SSOController, EnsureSsoEligibility, SSOService and tests in opts2026.
- LGU Connect docs/decisions/lc-024-host-adapter-contract.md and docs/decisions/lc-026-widget-credential-contract.md if present; backend services are authoritative for current wire shape.
- Current EDTS app.tsx and HandleInertiaRequests; new UI pages use DashboardLayout directly.

## Gates

PIV implement, validate and independent review. Focused backend tests, existing auth tests, frontend build, lint of changed PHP, diff whitespace check. Broaden tests only for concrete lifecycle risk. Live host logout must deny further widget operations without logging out independent Connect web sessions. All credentials remain private. No production deployment or synthetic production data.

## Known limits

Local Connect currently has a stale proxy container configuration; repair independently before host registration. Remote Connect has intermittent API 503 responses under investigation; preparing EDTS rollout does not certify production capacity. Staging/production hostname and SSO client values remain deployment inputs, never guessed defaults enabled in runtime.
