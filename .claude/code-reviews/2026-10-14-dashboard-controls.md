# Dashboard controls and attention review

Date: 14 October 2026. Scope: local Dashboard prototype and Storybook.

## Changes

1. Desktop sidebar collapses into a 72px icon rail. The button exposes its expanded state and controlled sidebar. Navigation keeps accessible names and tooltips. The account avatar remains available; the mobile Sheet stays fully expanded regardless of desktop state.
2. Workload count buttons and zeros use identical dimensions and centered numeric cells. Browser geometry confirmed identical vertical centers and 38px widths for PR/PO/VCH values in the first office row.
3. Attention uses a count banner and inline official Collapsible panel. The list shows current office, workflow step, elapsed business days, workflow target, and overdue/idle reason. It sorts by delay, supports search, follows office/year scope, and opens the selected document's route. Phone rows stack into labeled fields in a bounded scrolling area.
4. Notifications use an official nonmodal Popover dropdown. The bell displays two unread notices initially. Local read actions update/remove its numeric badge. Notification links open the correct record; read and empty states have stories.

## Product truth

Inspected `app/Services/EtaCalculationService.php` and `DashboardService.php` in OPTS. The application flags positive delay beyond a received step's expected_days, or idle time at/above its configured threshold. Completed/cancelled transactions are excluded by the application. Prototype records include only Created, In Progress, and Completed states, and use explicit demo timing metadata.

The multiple-attention fixture adds two simulated records to the base seven records: a PO held seven business days against a three-day target (four days overdue), and a voucher held five against two (three days overdue). The existing created PR is idle for three days and awaits receipt; its target is explicitly not started. The two-day idle threshold is a catalog fixture, not a claim about deployed configuration. No database rows or backend rules changed.

## Checks

- Workspace TypeScript: PASS.
- `pnpm build:all`: PASS for five catalogs; OPTS has 82 stories, including 18 Dashboard states.
- Local Docker catalog rebuilt and served artifact readback checked.
- Browser at 1637×1314 and 390×844: collapse/expand, mobile navigation, aligned values, nonmodal dropdown, unread/read actions, keyboard Escape, record-link focus, multiple attention, search, workflow details, all-clear state, and MACCO office scope passed. Phone had no horizontal overflow.
- Notification dropdown width was 366px in the 390px phone viewport, with no modal backdrop. Opening a linked record placed focus inside its details dialog.
- React review: hooks remain unconditional, list identifiers remain stable, immutable state updates preserve counts, canonical primitives own focus/keyboard behavior, and no new dependency or data-fetching effect was added.
- Design detector ran once; primary findings were zero. Literal size/color advisories remain local documented density/state treatments.

## Scoped review

Reviewed data, Dashboard composition, attention component, notification component, CSS, and stories. The collapsed account avatar initially inherited a generic span-hiding selector; the selector was narrowed to help-button text before final build. No unresolved technical finding remains in the reviewed prototype scope.

All processing, notifications, and timing examples are local fixtures. These checks do not constitute a live procurement MVP retest or application integration.
