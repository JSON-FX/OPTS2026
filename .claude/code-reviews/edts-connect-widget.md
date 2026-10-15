# EDTS Connect widget independent review

Reviewed 2026-10-15 against the uncommitted `feature/edts-connect-widget` tree. Scope includes all seven tracked modifications and sixteen new files present at review time, excluding this report. The tree is being updated by other agents; later changes require a focused re-review.

## Stats

- Files Modified: 7
- Files Added: 16
- Files Deleted: 0
- New lines: 1390
- Deleted lines: 0

## Findings

### P2 — Minimized launcher does not visibly indicate unread messages

severity: medium
file: public/js/lgu-connect-widget.js
line: 138
issue: Badge messages update only the accessible label, leaving the visible EDTS launcher unchanged.
detail: EDTS mounts this loader in `resources/js/connect-widget.ts:38` without an `onBadge` consumer. The loader updates its internal count and `aria-label`, but never renders the count or a visual unread marker. After a real `lgu-connect.widget.badge.v1` message with count 4, a deterministic DOM harness showed visible button content `[seal, "Connect"]` and the accessible label `Open LGU Connect, 4 unread messages`. A sighted employee working in EDTS receives no new-message indication after minimizing the widget. This repeats the notification gap the user previously asked to fix.
suggestion: Render a visible count in the canonical loader and copy the pinned result, or implement the supported host badge callback with a visible indicator. Keep the accessible label, clear at zero, and test a received badge while minimized. Update the loader checksum if changing the shared source.

### P2 — Shared introspection budget rejects ordinary concurrent widget activity

severity: medium
file: routes/connect.php
line: 14
issue: The 300-per-minute anonymous throttle pools every EDTS employee behind the Connect server's source IP.
detail: Connect sends cookie-free signed server requests, so Laravel's throttle signature uses route domain plus source IP, not the individual widget binding. `WidgetSessions::verify` introspects on each widget API request. The current frontend polls selected-conversation activity every five seconds and posts a heartbeat every fifteen seconds, already at least sixteen introspections per minute per foreground employee. Nineteen such employees exceed this shared 300 limit before typing, read acknowledgments, messages, or per-recipient outbox introspections. A direct framework middleware reproduction rejected request 301 with HTTP 429. Connect translates this to temporary authorization unavailability and retries/delays delivery. In addition, `docs/sso-directory-access.md` specifies an EDTS SSO consumer limit of 100/minute; each host introspection calls SSO again, so that upstream budget can fail at seven foreground employees and also starve ordinary EDTS navigation. This is a release-capacity defect, not justification to cache stale authorization.
suggestion: Give verified bindings an appropriate individual limit and maintain a separate coarse source limit sized for expected fanout. Document and provision the matching EDTS SSO consumer budget, with a bounded concurrent-user acceptance test. Preserve live authorization and fail-closed behavior. A local two-account demonstration alone does not establish staging/production readiness.

## Verified sound behavior

- Launch and revocation wire bodies and HMAC signatures match current Connect services. Exact origins and canonical references are validated.
- Introspection verifies signed raw content, timestamp, replay nonce, persisted revocation, live encrypted session binding, guard user, UUID, expiry and current SSO grant.
- Decryption matches Laravel's `EncryptedStore` and database session encoding. No bearer is cached in the host binding record.
- Confirmed authorization denial, successful identity replacement and host logout revoke the existing binding. Temporary upstream failure is distinguished from a denied grant.
- Public Inertia props contain origin, relative launch URL and identity only. Launch retains CSRF protection. The CSRF meta value is expected same-origin browser state, not an exposed SSO/connector secret.
- The Inertia integration preserves the iframe on ordinary navigation and destroys it on identity/configuration removal or change. Its revision check prevents a delayed script from mounting a logged-out identity.
- Vendored loader bytes match current Connect's canonical loader before proposed fixes.
- Environment examples remain disabled. Remote deployment and production acceptance are not claimed.

## Validation performed by reviewer

- PHP 8.4 focused ConnectPilot and Auth/SSOAuthentication suite: **39 tests, 329 assertions, passed** using the documented synthetic APP_KEY and SQLite memory configuration.
- Node frontend lifecycle suite: **3 tests, passed**.
- `git diff --check`: passed.
- Deterministic loader DOM reproduction for unread count: finding confirmed.
- Laravel throttle middleware with isolated in-memory cache: request 301 rejected with 429, finding confirmed.
- No operational database mutations, remote deployments or browser acceptance were performed by this reviewer.

An initial test command omitted the synthetic APP_KEY and therefore failed existing SSO tests with MissingAppKeyException. The corrected documented command passed; this was a test invocation issue, not an application finding.

## Finding resolution and final adapter review

Both P2 findings were resolved before local acceptance. The EDTS adapter consumes the pinned loader badge callback with a visible, accessible badge and lifecycle cleanup; six frontend tests pass. Introspection now uses a configurable coarse source budget plus a signed per-binding limit. The matching local SSO budget was adjusted only after observed exhaustion; rollout docs require independent measured remote budgets. Final focused primary authentication/widget suite:43 tests,744 assertions passed, including real local/staging/production app-environment datasets. Independent agent re-review found no additional adapter findings. Office-scale capacity remains a release gate.
