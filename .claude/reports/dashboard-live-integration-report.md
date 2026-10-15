# Live Dashboard implementation report

Date: 14 October 2026. Status: implemented and verified locally at https://opts.lgu.lan/dashboard. No commit, push or production deployment requested.

## Delivered

Approved Casebook Dashboard connected to existing Laravel services and current records. Shared document register and overview cover workload, activity, attention, pending receipts and notifications. Dashboard-specific shell keeps navigation/account behavior and actual SSO access. Compatible official shadcn primitives are reused without a React/Tailwind upgrade. See `docs/design/dashboard-live-parity.md` for the interaction matrix and final owner corrections.

Reference saved at https://storybook.lan/opts/?path=/story/start-here-approved-dashboard--approved-dashboard and https://storybook.lan/opts/prototypes/dashboard.html. Source/saved/served standalone HTML SHA-256: `aa5a6c1cb2f5062596af51edee8dc97beaf6e73d8e7376cb95a293d66cebf789`. The original snapshot remains immutable; live adaptations and owner clarifications are documented separately.

## Validation

- `php -d memory_limit=512M vendor/bin/phpunit`: 709 tests, 3423 assertions; zero failures/errors. Four PHP deprecations and 37 PHPUnit deprecations remain. The artisan runner used a 128MB subprocess limit and exhausted memory; direct PHPUnit with 512MB completed (130.50MB measured).
- `vendor/bin/pint --test --dirty`: seven PHP files pass.
- `npm run build`: typecheck and Vite build pass. Final Docker build runs the same command against final source.
- `git diff --check`: pass.
- Independent backend/access and frontend/motion reviews completed. Confirmed issues fixed: outside-scope attention, stale pending receipts, missing-record fallback, awaiting-receipt office labels, stale queue data, polling overwrite race and exit-content retention.
- Storybook workspace typecheck and all five catalog builds passed during reference save. Only OPTS catalog was refreshed in its running container.
- Browser desktop and 390px phone checks passed: scope/search/sort, queue isolation, long purpose wrapping, responsive overview, sidebar collapse, mobile navigation/help, customization, attention expansion/search, real notification overview/focus, completed route checks and notice variants. Pending receipts currently show the truthful empty state. Queue pagination is covered by regression fixtures; live dataset fits one page.
- Motion computed styles: dialog 180ms, backdrop160ms, Sheet220ms, dropdown150ms, sidebar160ms. Closed Help dialog retained its title with `op-dialog-exit`; keyboard focus returned to navigation. Notification overview returned focus to bell.
- Reduced-motion override reviewed in source; no Browser media emulation available in this session.

## Local readback

Only OPTS image/service rebuilt and recreated; container healthy. Image ID: `sha256:dfc00079e1bd8735960f2a6b6a46e23095543a57bdb6161853193b1255f2d187`.

Controller, service, Dashboard page/dialog, base CSS and motion CSS hashes match workspace/container. Served Dashboard JavaScript `Dashboard-ByUf4tI5.js` matches container SHA-256 `3f9e7581996c7f21987b838821b6b8fa9a1f0b768703409353b97e60a596f82b`. Served CSS `Dashboard-BdcVwFHh.css` matches `e0533a206946a7722ac01e5a4d24e668a56cc964cd236c477466db55508e830f`.

## Limits and scope

Real directory data, authorization and workflow services remain unchanged. UI verification did not create/endorse another procurement; this is not a claim of a fresh end-to-end procurement lifecycle run. Configured announcements support display/scheduling, not an in-app editor. Local notice QA was removed. No data reset or import was run. Test-only OfficeFactory abbreviation generation became unique after full-suite random collisions exposed the database constraint.
