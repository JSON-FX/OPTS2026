# Epic 4: Dashboard, Monitoring & Notifications

**Epic Goal:**

Build the comprehensive dashboard providing at-a-glance visibility into system-wide procurement activity with summary cards showing total counts for PR, PO, VCH, and Procurements. Implement office workload tables displaying transaction distribution across offices with drill-down capabilities, real-time activity feeds showing recent endorsements and completions, and stagnant transactions panels highlighting overdue and idle items requiring attention. Develop the notification system with bell icon badge counters for actionable alerts including out-of-workflow routes, received items awaiting action, overdue transactions, completions, and administrator notices. Enhance all list views with comprehensive search and filter capabilities by date range, office, status, category, fund type, and supplier. This epic delivers proactive monitoring and visibility tools enabling all users to understand system state, identify bottlenecks, and take timely action on items requiring attention. By epic completion, users have a powerful dashboard and notification system replacing manual status checking with automated alerting and visual analytics.

---

## Sub-Epic Breakdown

Epic 4 is split into three focused sub-epics for manageable delivery:

| Sub-Epic | Title | Stories | Dependencies |
|----------|-------|---------|--------------|
| **4.1** | [Dashboard & Summary Views](epic-4.1-dashboard-summary-views.md) | 4.1.1, 4.1.2, 4.1.3 | Epic 3 (all) |
| **4.2** | [Notification System Expansion](epic-4.2-notification-system-expansion.md) | 4.2.1, 4.2.2, 4.2.3, 4.2.4 | Story 3.8 |
| **4.3** | [Enhanced Search & Filtering](epic-4.3-enhanced-search-filtering.md) | 4.3.1, 4.3.2 | Epic 2, Story 3.9 |

### Recommended Implementation Order

1. **Epic 4.1** (Dashboard) — highest user value, replaces stub page
2. **Epic 4.2** (Notifications) — extends existing 3.8 infrastructure
3. **Epic 4.3** (Search/Filters) — enhances existing list pages, prepares for Epic 5 export

Sub-epics 4.1 and 4.2 can be developed in parallel as they have no cross-dependencies. Epic 4.3 is independent of both.

---

## Story Summary

| Story | Title | Sub-Epic | Dependencies |
|-------|-------|----------|--------------|
| 4.1.1 | Dashboard Summary Cards & Office Workload Table | 4.1 | Epic 2, Epic 3 |
| 4.1.2 | Dashboard Activity Feed & Stagnant Panel | 4.1 | 3.3, 3.9 |
| 4.1.3 | Dashboard SLA Performance Panel | 4.1 | 3.3, 3.8, 3.9 |
| 4.2.1 | Additional Notification Types (Received, Overdue, Completion) | 4.2 | 3.5, 3.6, 3.8, 3.9 |
| 4.2.2 | Admin Announcement System | 4.2 | 3.8 |
| 4.2.3 | Notification Preferences | 4.2 | 4.2.1, 4.2.2 |
| 4.2.4 | Real-Time Notification Delivery with Laravel Reverb | 4.2 | 4.2.1, 3.8 |
| 4.3.1 | Global Transaction Search Enhancement | 4.3 | 2.10, 3.9 |
| 4.3.2 | Standardized List Page Filtering & Sorting | 4.3 | 4.3.1, Epic 2 |

---

## PRD Requirements Coverage

| Requirement | Covered By |
|-------------|------------|
| **FR20** — Dashboard with summary cards, workload table, activity feed, stagnant panel | Stories 4.1.1, 4.1.2, 4.1.3 |
| **FR23** — Search/filter by date, office, status, category, fund type, supplier | Stories 4.3.1, 4.3.2 |
| **FR30** — Announcement system (Normal/Advisory/Emergency) | Story 4.2.2 |
| **FR31** — Notifications: out-of-workflow, received, overdue, completions, admin notices | Story 4.2.1 (extends 3.8), 4.2.2 |
| **Phase 2** — Real-time notification delivery via WebSockets (per technical-assumptions.md) | Story 4.2.4 |
| **NFR11** — SLA dashboards showing step delays vs expected_days | Story 4.1.3 |
| **NFR13** — Standard reports: turnaround, out-of-workflow, volume, stagnant | Stories 4.1.2, 4.1.3 |

---
