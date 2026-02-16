# Epic 4.1: Dashboard & Summary Views — Brownfield Enhancement

**Epic Goal:**

Replace the placeholder Dashboard page with a comprehensive command center providing at-a-glance visibility into system-wide procurement activity, office workloads, recent activity, stagnant transactions, and SLA performance metrics.

---

## Existing System Context

- **Current relevant functionality**: Dashboard.tsx is a stub showing "You're logged in!" — no dashboard data. All underlying data services exist: EtaCalculationService (delay/stagnant calculations), TimelineService (timeline data), TransactionAction model (full action history), Transaction model with ETA computed attributes.
- **Technology stack**: Laravel 12.x + React 18 + TypeScript + Inertia.js + shadcn/ui + Tailwind CSS
- **Integration points**: Transaction model (with ETA appends), EtaCalculationService, TransactionAction model, Procurement model, Office model, existing DelaySeverityBadge and CompactProgress components

## Enhancement Details

- **What's being added**: Full dashboard with summary cards, office workload table, activity feed, stagnant transactions panel, and SLA performance metrics
- **How it integrates**: New DashboardController aggregates data from existing models/services and passes to enhanced Dashboard.tsx via Inertia. Reuses existing components (DelaySeverityBadge, CompactProgress).
- **Success criteria**: Users see real-time procurement counts, office workloads, recent system activity, overdue/stagnant items, and SLA performance immediately on login

---

## Stories

### Story 4.1.1: Dashboard Summary Cards & Office Workload Table

**As a** user,
**I want** to see summary cards with total procurement/transaction counts and an office workload table on the dashboard,
**so that** I can quickly understand the current state of procurement activity across the organization.

**Key deliverables:**
- DashboardController with aggregated query methods
- Summary cards: Total Procurements, PR, PO, VCH — each with status breakdown (Created, In Progress, Completed, On Hold, Cancelled)
- Office Workload table: PR/PO/VCH counts per office, sortable, with drill-down links to filtered lists
- Role-scoped data: Viewers see all (read-only), Endorsers see their office highlighted, Admins see all with admin actions
- TypeScript interfaces for dashboard data structures
- Unit tests for aggregation queries

**Dependencies:** Epic 2 (Procurement/Transaction models), Epic 3 (workflow data)

---

### Story 4.1.2: Dashboard Activity Feed & Stagnant Panel

**As a** user,
**I want** to see recent system activity and a panel of stagnant/overdue transactions on the dashboard,
**so that** I can stay informed of what's happening and identify items that need immediate attention.

**Key deliverables:**
- Activity Feed: Last 20 endorsement/receive/complete actions system-wide, showing actor, action type, transaction reference, from/to office, timestamp
- Stagnant Transactions Panel: Transactions flagged as stagnant (overdue or idle), sorted by delay_days descending, with DelaySeverityBadge and quick-link to transaction detail
- Auto-refresh option or "last updated" timestamp
- Empty states for both sections
- Feature tests for dashboard data loading

**Dependencies:** Story 3.9 (ETA/stagnant calculations), Story 3.3 (TransactionActions)

---

### Story 4.1.3: Dashboard SLA Performance Panel

**As an** Administrator or Endorser,
**I want** to see SLA performance metrics on the dashboard,
**so that** I can identify bottleneck offices and monitor workflow efficiency.

**Key deliverables:**
- Average turnaround time per office (business days, across all workflow steps handled by that office)
- Out-of-workflow incident count (current month and total)
- Transaction volume summary: current month vs previous month, by category
- Performance indicators (green/yellow/red) based on average vs expected_days
- Admin-only: system-wide metrics; Endorser: own office metrics highlighted
- Feature tests for SLA calculations

**Dependencies:** Story 3.9 (ETA calculations), Story 3.8 (out-of-workflow data), Story 3.3 (action timestamps)

---

## Compatibility Requirements

- [x] Existing APIs remain unchanged — no existing Inertia routes modified
- [x] Database schema changes are backward compatible — no schema changes needed (read-only aggregation)
- [x] UI changes follow existing patterns — reuses shadcn/ui Card, DataTable, Badge components
- [x] Performance impact is minimal — aggregation queries should use database-level counts, not N+1 loading

## Risk Mitigation

- **Primary Risk:** Dashboard aggregation queries may be slow with large datasets
- **Mitigation:** Use database-level COUNT/SUM/GROUP BY queries, not Eloquent collection operations. Consider caching for expensive aggregations.
- **Rollback Plan:** Dashboard is a new page replacing a stub — rollback is simply reverting to the stub. No existing functionality affected.

## Definition of Done

- [ ] All 3 stories completed with acceptance criteria met
- [ ] Existing functionality verified — all current pages and features still work
- [ ] Dashboard loads within 2 seconds with test dataset
- [ ] Role-based data scoping works correctly (Viewer/Endorser/Admin)
- [ ] No regression in existing features
- [ ] Documentation updated (story files, epic list)
