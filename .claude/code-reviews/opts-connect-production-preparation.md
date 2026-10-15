# Code review: OPTS production Connect preparation

**Base:** e83ece8902c26ee903d0bc890d42c8096125f54a  
**Branch:** codex/opts-connect-production  
**Implementation scope excluding .claude artifacts:** 8 modified files, 13 added files, 0 deleted files; 1,738 added lines and 1 deleted line. Most additions reuse the existing adapter, canonical loader, tests and operational documentation.

## Result

Code review passed. No technical issues detected.

Reviewed the entire adapter/controller/config/routes/migration, Inertia integration, lifecycle hooks, pinned loader, tests, deployment examples/contract/CI and runbook. Checked HMAC raw-body signing, canonical binding/UUID validation, replay and timing limits, persistent encrypted sessions, fresh SSO authorization, durable revocation, outage semantics, dedicated secret separation and frontend identity/navigation lifecycle.

Disabled configuration short-circuits publication and service access. Browser launch keeps auth and CSRF; server introspection alone omits browser checks and verifies its signed current-session proof. Secrets/session IDs/bearers are absent from browser props and response proofs. Additive migration is compatible with the prior image and is retained during recovery. SSO offices/roles and procurement workflow authorization retain the existing implementation.

## Evidence

- Full PHP 840 tests / 5,137 assertions; focused adapter 24 tests / 634 assertions.
- Seven frontend tests, TypeScript/Vite build, 12-file Pint, deployment contract, workflow YAML and whitespace checks passed.
- Browser disabled-state inspection: zero loader/launcher/iframe and no captured errors.
- Canonical loader SHA256 matches the Connect owner contract at base 5c7ef4a324a6976fa42724e8dd9acd073ad4c7ca.

## Operational limits

This is an uncommitted source candidate. Production runtime remains the approved e83ece8 image. Missing Connect production registration, live functional acceptance and measured 300–500 concurrent-user capacity are explicit activation blockers, not waived tests. The default limits are guardrails and do not establish workload support. Existing PHP/PHPUnit deprecations and frontend build notices are recorded in the implementation report.
