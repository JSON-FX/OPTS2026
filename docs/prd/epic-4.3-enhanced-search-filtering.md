# Epic 4.3: Enhanced Search & Filtering — Brownfield Enhancement

**Epic Goal:**

Enhance all procurement and transaction list views with comprehensive search and filter capabilities (FR23) including date range, office, status, category, fund type, and supplier filters. Standardize the filtering experience across all list pages using the existing DataTable component and prepare filtered views for future data export (Epic 5).

---

## Existing System Context

- **Current relevant functionality**: Transaction search exists at `Transactions/Search` with basic filters (reference_number, category, status, date range, end_user_id, created_by_me, sort). Procurements index has basic pagination. Individual transaction type pages (PR, PO, VCH) have minimal filtering. `DataTable.tsx` component exists with sort/filter/pagination capability.
- **Technology stack**: Laravel 12.x + React 18 + TypeScript + Inertia.js + shadcn/ui + Tailwind CSS
- **Integration points**: Existing controllers (ProcurementController, PurchaseRequestController, PurchaseOrderController, VoucherController), existing DataTable component, existing TransactionSearchFilters type

## Enhancement Details

- **What's being added**: Expanded filter options on all list pages (office, fund type, supplier, date range, ETA/delay status), standardized filter bar component, saved/quick filter presets
- **How it integrates**: Extends existing controller index methods with additional query parameters. Enhances existing list page React components with filter bar. Reuses and extends TransactionSearchFilters type.
- **Success criteria**: Users can quickly find any procurement or transaction using any combination of available filters, and the experience is consistent across all list pages

---

## Stories

### Story 4.3.1: Global Transaction Search Enhancement

**As a** user,
**I want** to search and filter transactions by office, fund type, supplier, and delay status in addition to existing filters,
**so that** I can quickly find specific transactions matching my criteria.

**Key deliverables:**
- Extend TransactionSearchFilters: add `office_id`, `fund_type_id`, `supplier_id`, `delay_severity`, `is_stagnant` filter options
- Update transaction search controller to handle new filter parameters with efficient database queries
- Update search page UI: add filter dropdowns for office, fund type, supplier (loaded via Inertia shared data or page props)
- Add delay status filter: On Track / Warning / Overdue / Stagnant
- Procurement search enhancement: add similar filters to Procurement list page
- URL-based filter state (filters persist in query string for bookmarking/sharing)
- Feature tests for each new filter parameter

**Dependencies:** Story 2.10 (existing transaction search), Story 3.9 (delay severity data)

---

### Story 4.3.2: Standardized List Page Filtering & Sorting

**As a** user,
**I want** consistent filtering and sorting on all list pages (Procurements, PR, PO, VCH, Transactions),
**so that** I have a predictable experience regardless of which list I'm viewing.

**Key deliverables:**
- Reusable `FilterBar` component: configurable filter fields (dropdowns, date pickers, text search), reset button, active filter count badge
- Apply FilterBar to: Procurements index, PurchaseRequests index (if separate), PurchaseOrders index, Vouchers index
- Standardized sort options: date created, reference number, status, ETA, delay days
- Status filter with count badges (e.g., "In Progress (23)")
- "My Items" quick filter for Endorsers (transactions at their office)
- Persist filter state in URL query parameters
- Feature tests for filter combinations on each list page

**Dependencies:** Story 4.3.1 (filter infrastructure), existing list pages from Epic 2

---

## Compatibility Requirements

- [x] Existing APIs remain unchanged — extending existing index methods with optional additional parameters (backward compatible)
- [x] Database schema changes are backward compatible — no schema changes needed (filters use existing columns)
- [x] UI changes follow existing patterns — extends existing list pages, reuses DataTable component
- [x] Performance impact is minimal — filter queries use existing indexed columns; add indexes if needed for new filter combinations

## Risk Mitigation

- **Primary Risk:** Complex filter combinations may produce slow queries on large datasets
- **Mitigation:** Ensure database indexes exist for all filterable columns. Use query builder with conditional where clauses (only apply filters that are set). Test with realistic data volumes.
- **Rollback Plan:** Filter enhancements are additive — existing pages still work without filters applied. FilterBar component is optional and can be removed from individual pages independently.

## Definition of Done

- [ ] All 2 stories completed with acceptance criteria met
- [ ] All list pages have consistent filter/sort experience
- [ ] Filters persist in URL for bookmarking
- [ ] Filter queries are performant (< 500ms with 10K+ records)
- [ ] Existing list page functionality unchanged when no filters applied
- [ ] No regression in existing features
- [ ] Documentation updated
