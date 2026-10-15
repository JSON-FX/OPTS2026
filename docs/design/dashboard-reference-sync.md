# Dashboard approved reference sync

Date: 14 October 2026. Status: approved visual reference saved. Live integration is complete locally; see `dashboard-live-parity.md` and `.claude/reports/dashboard-live-integration-report.md`.

## Approval and scope

The owner approved the latest Casebook Dashboard for implementation in OPTS, with real application data and existing SSO roles and workflow rules. The owner also explicitly requested saving the latest prototype to Storybook. This record captures that reference before application adaptation. It does not claim application integration, live announcement publishing, realtime delivery, or a procurement MVP retest.

- Approved reference: [Start here / Approved Dashboard](https://storybook.lan/opts/?path=/story/start-here-approved-dashboard--approved-dashboard).
- Standalone interactive prototype: [Dashboard HTML](https://storybook.lan/opts/prototypes/dashboard.html).
- Storybook source: `/Users/jsonse/Documents/development/storybook-hub/projects/opts/`.
- Canonical official primitives: `/Users/jsonse/Documents/development/storybook-hub/packages/opts-ui/src/ui/`.
- Application snapshot: `docs/design/dashboard-prototype.html`.

The fixed approved story uses Casebook, Administrator, populated data, an expanded sidebar, unread notifications, and no announcement. Catalog preview controls are hidden. The existing 18 Dashboard scenarios remain available under Directions / Dashboard proposals. Civic Register and Office Board remain alternatives. All records, users, timing examples, announcements, and notification actions in this catalog are synthetic local fixtures.

## Included feedback

The reference includes a collapsible sidebar with account information in its footer, aligned workload numbers, a visible notification count and nonmodal dropdown, an expandable list for multiple overdue or idle documents, announcement variants, and workload/activity above the document register. Office selectors use abbreviations; document timelines use full office names. Viewer fixtures remain read-only. Endorser processing previews remain disabled until real application eligibility is available.

## Compatibility and source sync

The catalog uses React 19, Storybook 10, and Tailwind 4. OPTS uses React 18, Laravel 12, Inertia, and Tailwind 3. Adapt this visual and interaction reference through compatible application components. Replace fixture data with application services, keep policy checks on the server, and retain existing SSO role and office eligibility. This source sync is explicit because the Hub working tree includes unrelated project changes and no commit was requested.

All hashes are SHA-256 and paths below are relative to `projects/opts/` in the Hub.

| Source | SHA-256 |
| --- | --- |
| `src/dashboard-prototype.tsx` | `4b5ad30633e615c50af686158f0da3036ddda58934b2ac4e49944d23034172d8` |
| `src/catalog.css` | `a37f807a335fbe88f33644dc8d86d555f91c888bb01acebdea6d6c094683a2a4` |
| `src/dashboard-data.ts` | `d2f7f01d36d19c1a651ec13fc5ee82dff1a42fccae5a1d1d40f3b5dd7ffd2e90` |
| `src/dashboard-attention.tsx` | `c639aad1866494ff882916ce85cb76aa7586d4cf0ed8ad8bd20e510caa4d56b1` |
| `src/dashboard-notifications.tsx` | `16a735450a280c25751e10afea0f340f18e3f90f2648f6dcbbc4f8a18e6660d0` |
| `src/dashboard-announcement.tsx` | `47aaefbe151c3cb51a6480ba08b2c1faf10f6db0f58753d27d4dc2d3c5fadb79` |
| `src/dashboard-approved.stories.tsx` | `850dfa3cfaa656c95f93b61fb7438954bca9a444e86a039c93af31a3729e3be2` |
| `src/dashboard.stories.tsx` | `f36e702a0e3efa0319b73ed311cede6d11bf5ea4dc2f398431eafe795ecdf59a` |
| `src/prototype-entry.tsx` | `fb4b371cd559d5dbeb0c6d0f521ace814aa1b29afa52e7d157c9569890cedfcc` |
| `public/prototypes/dashboard.html` | `aad98c1ad78b245ee04090d30ae7f9a8ae5cf243bf8bf6b86e81131eb4b73f62` |

The standalone HTML hash remains `aad98c1ad78b245ee04090d30ae7f9a8ae5cf243bf8bf6b86e81131eb4b73f62`. The saved HTML, local build, running container, and HTTPS response have matching bytes.

## Verification

- `pnpm typecheck`: passed across the workspace.
- `pnpm build:all`: passed for five catalogs. OPTS now contains 83 stories, including 19 Dashboard stories (one approved reference plus 18 scenario stories).
- Only the rebuilt OPTS catalog was copied to `/usr/share/nginx/html/opts/` in the existing `storybook-hub` container. Other served project directories were not replaced.
- HTTPS artifact readback and container SHA-256 match the source HTML above.
- Browser checked the approved story at 1280px and 390px. Preview controls and announcements were absent in the default reference. No horizontal overflow was present. Workload/activity preceded the document register. Mobile navigation included the account menu. Temporary viewport overrides were reset.
- Desktop and mobile screenshots: `projects/opts/docs/dashboard-approved-reference-desktop.png` and `projects/opts/docs/dashboard-approved-reference-mobile.png` in the Hub.

No Git commit, push, production deployment, or application data mutation is part of this reference save.

## Live application follow-through

The saved prototype remains the approved source snapshot. The owner later clarified that workload numbers must open an isolated modal without changing the Dashboard background. The live application also wraps longer real purpose text and uses short, reduced-motion-aware transitions. These adaptations and their checks are recorded in `dashboard-live-parity.md`.

15 October annotation revision: non-action document row areas now select the adjacent preview, retaining the reference button and arrow/modal behaviors. Current prototype TSX/HTML hashes above reflect this accepted correction.
