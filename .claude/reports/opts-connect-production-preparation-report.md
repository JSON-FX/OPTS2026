# Implementation report: OPTS production Connect preparation

**Plan:** .claude/plans/opts-connect-production-preparation.md  
**Branch:** codex/opts-connect-production  
**Status:** COMPLETE for source preparation; production widget activation remains gated.

## Summary

Prepared the existing signed Connect host adapter on approved OPTS e83ece8. Default false, exact environment examples and tests keep Connect absent until explicit later configuration and readiness acceptance. Production remains on e83ece8; no runtime files, SSO grants, capacity budgets, registrations or databases were changed by this preparation. No commit, push or deployment was performed.

## Completed

- Reused ConnectPilotHost/controller/routes, append-only revocation migration, pinned canonical loader and Inertia adapter from the independent original checkout.
- Added only required callback/logout/denial revocation hooks and shared widget prop to the release application.
- Prepared exact production URLs and proposed opts-production / opts-production-v1 registration IDs, blank secret, and CONNECT_PILOT_ENABLED=false. Staging example remains reference only.
- Recorded canonical loader digest and full Connect base commit in deploy/connect-widget/loader-manifest.json. Coordinated contract and private future handoff with the LGU Connect chat.
- Added disabled-state, production endpoint, CSRF, guest-authentication and distinct-secret regression tests. Added frontend tests and changed PHP formatting checks to existing CI.
- Documented future release, private key provisioning, two-employee integration checks, required 300–500 workload measurement, activation decision and recovery.

## Validation

- Full PHP suite: **840 tests, 5,137 assertions passed** on local PHP 8.5.11. Four existing PHP deprecations and 37 PHPUnit metadata deprecations remain. Future CI retains PHP 8.2/8.3/8.4 matrix; this unpushed candidate has no hosted run yet.
- Focused Connect suite: **24 tests, 634 assertions passed**. Includes signed launch/introspection/replay, logout/identity/grant revocation, outage recovery, encrypted session validation, per-binding limits and exact production origins using HTTP fakes.
- Frontend adapter: **7 tests passed**. No loader, badge, mount or network call with absent/null config; navigation preserves a same-identity widget and delayed callbacks cannot revive the previous identity.
- Amount formatting: **12 checks passed**.
- TypeScript/Vite production build: **passed**. Existing browser-database age notices and font-path build warnings remain.
- Pint: **12 files passed**, including the three existing delivery files and nine adapter-related PHP files.
- Deployment contract: **passed** for existing isolation/parity, immutable-image CI, disabled connector examples, distinct environment origins and exact pinned loader digest.
- Workflow YAML parsing and git diff --check: **passed**.
- Browser: local disabled candidate rendered the approved entry page. DOM had **zero Connect loaders, zero launchers and zero iframes**, with no captured browser errors. Authenticated disabled-page props and no Connect upstream traffic are covered by PHP tests; no synthetic production identity or business record was created. Browser used as required; no Playwright fallback was needed.
- Read-only MIS inspection: production remains on runtime image **sha256:2b20ab7aad45d93d9036724fc78cb775fe2add25cc94130b2914ed87a3aff41a**, healthy. No remote mutation or optional staging release occurred.
- Local preview server stopped and temporary browser tab closed after acceptance.

## Review and deviations

Source review: .claude/code-reviews/opts-connect-production-preparation.md. One extra guard rejects reuse of the SSO client secret as the connector key, enforcing the existing Connect contract. Other signing/session/revocation behavior is reused. No new dependency or authentication system was introduced.

Local PHP 8.5 deprecations appeared during initial Browser rendering. The temporary preview process disabled display of those existing deprecations; no application/database configuration was changed to address them. No live cross-application enabled-widget test or capacity claim is made.

## Pending operational gates

1. Connect production image/readiness and exact disabled host/key registration; private key file is proposed, not provisioned.
2. Approved future source publication and same-image release with fresh backup/off-server restore and schema review.
3. Coordinated live functional integration and measured 300–500-user workload on target hardware, with agreed SSO/Connect/OPTS budgets and latency/error limits.
4. Explicit activation decision after those evidence records pass. Keep widget and Connect host/key disabled until then.

## Evidence

- File fingerprints: .claude/reports/opts-connect-production-snapshot.json.
- Disabled Browser capture: /Users/jsonse/.local/share/mis-deployment-receipts/opts/opts-connect-disabled-candidate.jpg.
- Local command output: /tmp/opts-connect-full-validation.log and /tmp/opts-connect-frontend-build.log.
- Activation/recovery guide: docs/connect-widget-rollout.md.
