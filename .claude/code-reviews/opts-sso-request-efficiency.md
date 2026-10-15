# OPTS SSO request efficiency review

## Stats

- Files modified: 13
- Files added: 3
- Files deleted: 0
- Lines added: 559
- Lines deleted: 30

## Scope

Reviewed feature source, full changed files/tests and the implementation plan. The independent reviewer performed no implementation edits. The original dirty development checkout and production remain untouched by this source work.

## Result

Code review passed. No technical issues detected.

Request attributes and a digest of exact token/API/client/secret bind successful response reuse to one inbound request. Plain authorization remains fresh, as does every new signed introspection request. UUID checks, current directory identity, office/role enforcement and denial-driven revocation remain intact. No successful permission response or bearer is placed in shared cache.

The failure-only shared cooldown uses a lock to preserve longer concurrent deadlines. In-flight successes cannot erase cooldowns; denied employees do not create client-wide failure state. Temporary failures preserve valid host sessions/bindings and carry bounded Retry-After. Signed nonces remain one-use even on failure. Stateless introspection excludes web middleware; browser launch retains authentication/CSRF.

The ProfileController change is one concat-spacing correction required by the full Pint gate and has no semantic change. The whole suite was rerun afterward. Directory writer coordination is explicitly deferred, not represented as fixed.

## Evidence and limits

Parent validation passed 858 backend tests / 5,321 assertions, full 270-file Pint, TypeScript/Vite build, 7 widget frontend tests, 12 amount checks and MIS contract/diff checks. The independent reviewer did not separately rerun those suites or exercise real database/Redis concurrency.37 PHPUnit deprecations are reported. Source validation is not browser, deployment, rollout or 300–500-user acceptance.
