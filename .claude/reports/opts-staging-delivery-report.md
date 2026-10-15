# OPTS staging delivery preparation

Plan: `.claude/plans/opts-staging-delivery.md`. Release checkout: isolated `codex/opts-staging-ui` worktree. Deployment state: PREPARED; remote activation pending operator access, DNS and credentials.

## Changes

Approved Dashboard, Procurements, PR/PO/voucher workspace, yearly reference allocation, mandatory scan handoffs, compact branded QR download/printing and supporting evidence are selected for staging. The earlier approved SSO-directory baseline is retained. Independent Connect pilot work remains in the original dirty checkout; its routes, server integration, client and schema are excluded.

New `mis-image.yml` preserves existing workflows and gates the full-SHA linux/amd64 image export on PHP 8.2/8.3/8.4, frontend type/build, peso-input checks and deployment contract validation. Only staging pushes upload a retained image/checksum artifact. No CI server credential is added. The MIS registration follows staging rather than default main because the owner explicitly requested staging.

Operator templates cover staging.opts.lguquezon.local and future opts.lguquezon.local, distinct database/storage/queues/credentials, trusted PHP CA configuration, secure host-only encrypted sessions, exact SSO callbacks, proxy/WebSocket routing, read-only readiness and manual promotion. Explicit public Reverb configuration is supplied at runtime so the tested image is reusable. Without that setting, local Vite fallbacks remain unchanged. Container bootstrap seeds roles only and workers use www-data.

## Validation

- Isolated application suite: 813 tests /4,456 assertions passed. Four runtime and37 PHPUnit deprecations remain. Local PHP8.5 exceeds one locked dependency upper bound; install retained the lock with `--ignore-platform-req=php+`. CI tests supported PHP8.2/8.3/8.4 and builds PHP8.4 image. No dependency update was made for this local mismatch.
- New deployment/runtime checks: six tests /22 assertions passed; mixed hosts, insecure sessions and upstream details fail safely.
- TypeScript/Vite and numeric peso input checks passed.
- Scoped Pint and shell syntax passed. Compose contract verifies both templates without runtime secrets. MIS helper registration validation passed.
- Initial isolated suite exposed reliance on an existing Vite manifest. Backend TestCase now disables Vite; frontend checks independently build assets. This keeps clean CI tests independent of a developer build directory.
- Existing feature technical/visual reviews, exact-reference decode records and desktop/mobile proof remain linked under docs/design. QR hardware acceptance is still pending.
- Native local image build passed. Final technical review found one callback access-log leak in the inherited inner Nginx format. Both inner and prepared outer formats now omit query strings and referrers. The contract check asserts that rule. Final image/review and remote exact-SHA workflow evidence follow below.

## Operational boundaries

Only read-only server inventory was performed. No OPTS environment exists on MIS. SSH works, but sudo requires interactive authentication. No protected registration, runtime secret, certificate, proxy config, SSO grant, production resource or database was modified. DNS is owned by the user. The source templates and operator instructions are concrete; live readiness/login cannot be claimed until installed.

The production target is a prepared manual-promotion destination, not a running service or an authorization to release. First installation has no previous OPTS image rollback. Subsequent MIS image rollback preserves database changes. Future production requires fresh backup/restore proof and approval of the exact staging artifact.

## AI layer changes

Existing feature plans, reviews and implementation reports are committed as delivery evidence. The staging plan/report describe source isolation, inherited MIS architecture, commands, acceptance gates and intentional deviations. No global skill, memory, DESIGN.md or unrelated project instruction changed.

## Final image check

Final native local image built successfully. Inner Nginx and both prepared proxy snippets pass nginx -t using an isolated disposable certificate fixture; its key was removed. This proves syntax, not trusted live TLS. The public Reverb build-ARG warning concerns a public client key, not a server secret; MIS supplies no build arguments.
