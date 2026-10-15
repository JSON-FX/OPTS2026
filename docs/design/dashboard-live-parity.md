# Dashboard interaction parity

Date: 14 October 2026. Local implementation uses the approved Casebook reference saved in Storybook. The catalog control strip and alternative designs remain reference tools only.

| Area | Live implementation | Verification |
| --- | --- | --- |
| Navigation | Collapsible desktop sidebar, icon tooltips, account footer, mobile Sheet, real account/admin links, skip link | Browser desktop/390px; 72px collapsed width |
| Notifications | Numeric unread badge, nonmodal dropdown, read/mark-all, record overview and focus restoration, existing Reverb updates | Browser real notification; ownership/link regression tests |
| Scope and customization | Reporting year, all/my/selected office, abbreviated selectors, four section preferences, real counts | Browser filters/customization; service tests |
| Announcements | Configured updates/reminders/maintenance/emergency, details, versioned dismissal, emergency remains visible | Temporary local config UI checks; removed afterward; scheduling tests |
| Attention | One/multiple summary, collapsible list, search/clear, workflow target/idle reason, document overview | Browser existing attention record; multiple-record backend tests |
| Workload | Workload/turnaround tabs, aligned numeric actions, queue modal, zeros inert, back-to-workload | Browser; equal number geometry; isolated queue regressions |
| Activity | Latest actions, expand more, correct real document overview | Browser existing voucher and PR |
| Register | Instant server search, category, global sort, pagination, selection, keyboard office tooltip, responsive overview | Browser search/sort/overview; backend pagination tests |
| Document context | Full office names, actual current holder, route/checks, attention, existing policy-derived action links | Browser real completed/incomplete records; eligibility/handoff tests |
| Secondary data | Volume, annual incidents, real footer/year, 30-second poll | Browser/service tests |
| Motion | Dialog 180ms, backdrop/sidebar 160ms, Sheet 220ms, dropdown 150ms; stable exit content | Browser computed timing and closed-state exit; CSS reduced-motion review |

## Final owner corrections

| Before | After | Why |
| --- | --- | --- |
| Long purpose inherited nowrap in the queue | Fixed table columns and wrapped purpose/reference text in both register and queue | Full content stays readable without horizontal scrolling |
| Workload click changed background scope | Independent queue office/category/search/sort/page and partial response | Dashboard totals, scope and register remain stable |
| Modals changed instantly | Short opacity/scale transitions, matching backdrops and mobile/menu motion | Makes opening and closing easier to follow |

Queue search with no matches left all-office totals at 3 procurements, 3 PRs, 2 POs and 2 vouchers. The underlying register retained seven rows and an empty search. Queue-to-overview-to-queue retained its context. Background polling excludes queue props and preserves the current URL. Rows cannot be selected while their replacement is loading.

At 1637px, queue dialog scroll/client widths were both 798px; table wrapper widths were both 750px. At 390px, dialog widths were both 358px, wrapper widths both 310px, and page widths both 390px. The long purpose wrapped on both screens. No transaction was created or processed during this UI verification.

Reduced-motion rules disable animations/transitions on actual portal roots. This was reviewed in source; this Browser session did not expose reduced-motion emulation.

Announcement publishing currently uses `config/opts.php` deployment configuration. No default notice or local QA notice remains published. An in-app announcement editor is outside this implementation.

SSO role mapping, office assignment and workflow rules remain authoritative. Guest maps to Viewer; standard maps to Endorser; administrator/super_administrator maps to Administrator. Existing processing destinations recheck authorization and workflow eligibility.

Proofs: `dashboard-live-desktop.png`, `dashboard-live-queue-desktop.png`, `dashboard-live-queue-mobile.png`, `dashboard-live-mobile-dialog.png`.
