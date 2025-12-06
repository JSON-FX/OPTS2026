# Epic 0.3: shadcn/ui Procurement & Finalization - Brownfield Enhancement

**Epic ID:** Epic-0.3
**Epic Type:** Brownfield Enhancement (UI Layer Migration - Phase 3)
**Priority:** High
**Status:** Ready
**Created:** 2024-11-04
**Dependencies:** Epic 0.1 (requires layouts and component library)
**Blocks:** None (can run parallel with Epic 0.2)

---

## Epic Goal

Complete the shadcn/ui migration by updating all procurement and transaction management pages (Procurements, Purchase Requests, Purchase Orders, Vouchers, Dashboard) with DataTable and Form components, run comprehensive testing, update documentation, and remove all legacy components.

---

## Epic Description

### Existing System Context

**Current Relevant Functionality:**
- **Procurement CRUD:** List, Create, Edit, Show pages for procurements (Story 2.3)
- **Purchase Request Management:** PR creation/editing linked to procurements (Story 2.5)
- **Purchase Order Management:** PO creation/editing linked to PRs (Story 2.7)
- **Voucher Management:** VCH creation/editing linked to POs (Story 2.8)
- **Procurement Detail View:** Shows procurement with linked PR/PO/VCH transactions (Story 2.9)
- **Transaction Search:** Global search/filter across all transactions (Story 2.10)
- **Dashboard:** Summary stats, activity feed, workload tables

**Technology Stack:**
- Already migrated: Layouts (Epic 0.1), Admin pages (Epic 0.2)
- DataTable pattern established in Epic 0.2
- Complex forms with business rule validations

**Integration Points:**
- Business rule services (ProcurementBusinessRules, ReferenceNumberService)
- Laravel validation with complex dependencies
- Inertia forms with nested data
- Toast notifications for success/error
- RBAC: Endorser/Administrator for create/edit, Viewer for read-only

### Enhancement Details

**What's Being Added/Changed:**

This epic **completes the migration** by:

1. **Procurement Pages:** Migrate CRUD interfaces with DataTable, DatePicker, currency inputs
2. **Transaction Pages:** Migrate PR/PO/VCH forms with Select dropdowns, Cards for summaries
3. **Detail Views:** Use Card, Badge, Tabs/Accordion for transaction displays
4. **Search Interface:** Advanced DataTable with Command component for search
5. **Dashboard:** Migrate stats cards, activity tables
6. **Final Cleanup:** Remove legacy components, run audits, update documentation

**How It Integrates:**
- **Layout Inheritance:** Uses AuthenticatedLayout from Epic 0.1
- **DataTable Pattern:** Follows pattern from Epic 0.2
- **Business Logic Unchanged:** Controllers, services, validation rules identical
- **RBAC Preserved:** Endorser/Admin create/edit, Viewer read-only

**Success Criteria:**
- ✅ All procurement/transaction pages migrated
- ✅ All tables are DataTables with full features
- ✅ All forms use shadcn components
- ✅ Detail views use Card/Badge/Tabs
- ✅ Dashboard migrated with stats Cards
- ✅ Zero legacy components remaining
- ✅ Zero mixed imports detected
- ✅ Zero console errors across all pages
- ✅ All business rules still enforced
- ✅ Comprehensive testing complete
- ✅ Documentation updated

---

## Stories

### **Story 0.3.1: Procurement & Transaction Pages Migration**

**Description:** Migrate Procurement CRUD pages and all transaction management pages (PR, PO, VCH) to shadcn components.

**Key Tasks:**

**Procurement Pages (Story 2.3 update):**
- **Index.tsx:**
  - Replace table → DataTable
  - Add search Input in toolbar
  - Add Select filters (Status, Office, Particular, Date Range)
  - Add "My Procurements" Checkbox filter
  - Display status as Badge
  - Display ABC Amount with currency formatting
  - Add actions DropdownMenu
- **Create.tsx:**
  - Replace inputs → Form components
  - Use Select for End User, Particular dropdowns
  - Use Textarea for Purpose field
  - Use Input with currency formatting for ABC Amount
  - Use DatePicker for Date of Entry
  - Show validation with FormMessage
- **Edit.tsx:**
  - Same as Create but with locked fields
  - Add Tooltip for "Cannot change End User/Particular after transactions created"
  - Use Alert for warnings
- **Show.tsx:**
  - Use Card for procurement details
  - Display status with Badge
  - Show linked transactions (handled in Story 0.3.2)

**Purchase Request Pages (Story 2.5 update):**
- **Create.tsx:**
  - Card for read-only procurement summary
  - Select for Fund Type
  - Form validation display
- **Edit.tsx:**
  - Similar structure, Fund Type can update
  - Alert for inactive fund type warning
- **Show.tsx:**
  - Card for PR details
  - Badge for status
  - Link to procurement (Card with Separator)

**Purchase Order Pages (Story 2.7 update):**
- **Create.tsx:**
  - Card for PR summary
  - Select for Supplier
  - DatePicker for PO Date
  - Input for PO Number
- **Edit.tsx:**
  - Similar structure
- **Show.tsx:**
  - Card for PO details
  - Badge for status

**Voucher Pages (Story 2.8 update):**
- **Create.tsx:**
  - Card for PO summary
  - Input for Payee, Voucher Number
  - DatePicker for Voucher Date
- **Edit.tsx:**
  - Similar structure
- **Show.tsx:**
  - Card for VCH details
  - Badge for status

**Acceptance Criteria:**
- [ ] Procurement list uses DataTable with filters
- [ ] All filters functional (status, office, particular, date range, "my procurements")
- [ ] ABC Amount displays as currency (₱#,###.##)
- [ ] Status displays as Badge
- [ ] Create/Edit forms use shadcn components
- [ ] DatePicker works for date fields
- [ ] Currency Input formats properly
- [ ] Locked fields show Tooltip explanations
- [ ] PR/PO/VCH forms use Card for summaries
- [ ] All Select dropdowns populated correctly
- [ ] Business rules still enforced (canCreatePR, etc.)
- [ ] Toast notifications work
- [ ] RBAC enforced (Endorser/Admin create/edit, Viewer read-only)
- [ ] Zero console errors
- [ ] Playwright tests pass

**Testing:**
- Playwright: Test procurement CRUD operations
- Playwright: Test PR/PO/VCH creation flows
- Playwright: Test business rule validations
- Playwright: Test RBAC (Viewer sees read-only)
- Playwright: Screenshot all pages at all viewports

---

### **Story 0.3.2: Procurement Detail View & Transaction Timeline Migration**

**Description:** Migrate Procurement detail page (Story 2.9) with linked transactions using Card, Tabs/Accordion, and timeline components.

**Key Tasks:**
- **Procurement Detail Section:**
  - Card for main procurement info
  - Badge for status
  - Separator for visual sections
- **Transaction Sections:**
  - Implement Tabs for PR/PO/VCH navigation OR
  - Implement Accordion for expandable sections
  - Each transaction displays in Card
  - Badge for transaction status
  - Links navigate to transaction Show pages
- **Timeline View:**
  - Create custom timeline component with shadcn styling
  - Show procurement lifecycle: Created → PR → PO → VCH → Completed
  - Highlight current stage
  - Use Separator between timeline items
  - Add date/time stamps
- **Action Buttons:**
  - "Add Purchase Request" button (if canCreatePR)
  - "Add Purchase Order" button (if canCreatePO)
  - "Add Voucher" button (if canCreateVCH)
  - Use Button with appropriate variants
- Test responsive layout
- Verify navigation links work

**Acceptance Criteria:**
- [ ] Procurement detail uses Card
- [ ] Transaction sections use Tabs or Accordion
- [ ] Each transaction displays in Card
- [ ] Status badges display correctly
- [ ] Timeline component shows lifecycle
- [ ] Current stage highlighted
- [ ] Action buttons conditional (based on business rules)
- [ ] Links navigate correctly
- [ ] Responsive at all breakpoints
- [ ] Zero console errors
- [ ] Playwright tests pass

**Testing:**
- Playwright: Navigate to procurement detail
- Playwright: Click transaction links
- Playwright: Test action button visibility (based on user role)
- Playwright: Screenshot at mobile/tablet/desktop

---

### **Story 0.3.3: Transaction Search & Dashboard Migration**

**Description:** Migrate transaction search/list page (Story 2.10) and Dashboard with advanced DataTable features and stats cards.

**Key Tasks:**

**Transaction Search/List:**
- Replace table → DataTable with advanced features
- Implement Command component for advanced search
- Add DateRangePicker for date filters
- Add Select for category filter (PR/PO/VCH)
- Add Select for status filter
- Add Input for reference number search
- Add Select for office filter
- Display results with Badge for status
- Add actions DropdownMenu
- Implement pagination

**Dashboard:**
- **Stats Cards:**
  - Replace custom stats → Card components
  - Total Procurements, Active PRs, Active POs, Active VCHs
  - Use Badge for counts
- **Activity Tables:**
  - Recent activity → DataTable (simple, no filters)
  - Workload by office → DataTable
- **Loading States:**
  - Add Skeleton components during data fetch
- **Charts (if applicable):**
  - Consider shadcn Chart components
- Maintain dashboard responsiveness (grid layout)

**Acceptance Criteria:**
- [ ] Transaction search uses DataTable
- [ ] Command component for advanced search works
- [ ] DateRangePicker filters transactions
- [ ] All filters functional
- [ ] Search by reference number works
- [ ] Dashboard stats use Card
- [ ] Counts display with Badge
- [ ] Activity tables use DataTable
- [ ] Skeleton displays during loading
- [ ] Responsive grid layout
- [ ] Zero console errors
- [ ] Playwright tests pass

**Testing:**
- Playwright: Test transaction search with filters
- Playwright: Test date range filtering
- Playwright: Test Command search functionality
- Playwright: Test dashboard loads without errors
- Playwright: Screenshot dashboard at mobile/tablet/desktop

---

### **Story 0.3.4: Testing, Documentation & Final Cleanup**

**Description:** Run comprehensive testing suite, execute final audits, update all documentation, remove legacy components, and validate migration success.

**Key Tasks:**

**Comprehensive Testing:**
- Run full Playwright test suite (all 30+ pages)
- Test at all breakpoints (375px, 768px, 1920px)
- Check console logs on every page (zero errors required)
- Visual regression: Compare baseline vs migrated screenshots
- Test all user roles (Viewer, Endorser, Administrator)
- Test all CRUD operations
- Test all business rule validations
- Test all navigation flows

**MCP Audit:**
- Run `mcp__shadcn__get_audit_checklist()`
- Complete all audit items
- Verify component usage patterns
- Check for unused components

**Legacy Component Cleanup:**
- Delete `resources/js/Components/*.tsx` (legacy components)
- Keep only `resources/js/components/ui/*` (shadcn)
- Remove old layouts if backup copies exist
- Search codebase for mixed imports (should be zero)
- Verify no references to deleted components

**Documentation Updates:**
- Update README.md with new component structure
- Create component usage guide (`docs/component-guide.md`)
- Document patterns:
  - DataTable usage pattern
  - Form validation pattern
  - Toast notification pattern
  - Modal/Dialog pattern
  - RBAC component pattern
- Update architecture docs if needed
- Add migration notes for future developers

**Performance Validation:**
- Run `npm run build -- --analyze`
- Verify bundle size increase ≤ 10%
- Check for unused CSS (Tailwind purge)
- Verify tree-shaking working

**Final Checklist:**
- [ ] All 8 success criteria from original Epic 0 goal met
- [ ] Zero console errors across all pages
- [ ] Zero mixed imports detected
- [ ] All legacy components deleted
- [ ] Documentation complete

**Acceptance Criteria:**
- [ ] All Playwright tests pass (100% success rate)
- [ ] Visual regression tests pass
- [ ] Zero console errors on all pages
- [ ] MCP audit checklist complete
- [ ] Legacy components deleted from codebase
- [ ] Documentation updated (README, component guide)
- [ ] Bundle size within acceptable limits (≤10% increase)
- [ ] Performance metrics acceptable (load time <3s)
- [ ] Accessibility maintained (WCAG AA)
- [ ] All roles tested (Viewer, Endorser, Admin)
- [ ] All CRUD operations functional
- [ ] All business rules still enforced

**Testing:**
- Playwright: Full regression suite on all pages
- Playwright: Test all user roles
- Playwright: Screenshot comparison (baseline vs final)
- Manual: Code review for mixed imports
- Manual: Bundle analysis review
- Manual: Accessibility audit

---

## Compatibility Requirements

- ✅ **All APIs Unchanged:** Controllers, routes, Inertia responses identical
- ✅ **Database Unchanged:** Zero database impact
- ✅ **Business Logic Preserved:** All services, validations, RBAC intact
- ✅ **Performance Acceptable:** Bundle size increase ≤10%, load times maintained
- ✅ **Accessibility Maintained:** WCAG AA compliance preserved
- ✅ **Browser Compatibility:** No regression in supported browsers

---

## Risk Mitigation

### Primary Risk: Complex Forms Break Business Rule Validations

**Description:** Procurement/transaction forms have complex business rules (canCreatePR, dependency checks). Migration could break validation display.

**Mitigation Strategy:**
1. Test all business rule scenarios explicitly
2. Verify FormMessage displays custom validation errors
3. Test dependency chains (PR → PO → VCH)
4. Maintain existing Laravel Form Request validation
5. Add explicit Playwright tests for validation flows

**Rollback Plan:**
- Revert individual pages if validation breaks
- Can rollback to Epic 0.1/0.2 state if critical
- Git checkpoints at each story

### Secondary Risk: Performance Degradation with Large Datasets

**Description:** DataTable with 100+ procurements could cause slow rendering.

**Mitigation Strategy:**
1. Server-side pagination (50 per page)
2. Lazy load transaction details
3. Optimize eager loading in controllers
4. Monitor performance metrics during testing
5. Consider virtual scrolling if needed

---

## Definition of Done

- ✅ All 4 stories completed with acceptance criteria met
- ✅ All procurement/transaction pages migrated
- ✅ Dashboard migrated with stats cards
- ✅ All legacy components removed
- ✅ Zero mixed imports detected
- ✅ Zero console errors across all pages
- ✅ Comprehensive testing complete (Playwright + manual)
- ✅ Visual regression tests pass
- ✅ MCP audit complete
- ✅ Documentation updated
- ✅ Bundle size acceptable
- ✅ Performance metrics acceptable
- ✅ Code review approved

---

## Dependencies

**Requires:** Epic 0.1 complete (layouts, component library)

**Blocks:** None (independent of Epic 0.2, but can run parallel after Epic 0.1)

---

## Timeline Estimate

- **Total Stories:** 4
- **Estimated Duration:** 1.5 weeks (60 hours)
- **Story Breakdown:**
  - Story 0.3.1: 3 days (24 hours) - Procurement & transaction pages (12+ pages)
  - Story 0.3.2: 2 days (16 hours) - Detail view & timeline
  - Story 0.3.3: 2 days (16 hours) - Search & dashboard
  - Story 0.3.4: 1 day (8 hours) - Testing & cleanup (+ buffer for issues)

---

## Related Documentation

- **Architecture:** `docs/architecture-shadcn-migration.md` (Phases 4-8)
- **Checklist:** `docs/shadcn-migration-checklist.md` (Phases 4-8)
- **Story Updates:** `docs/shadcn-story-updates.md` (Stories 2.3-2.10, Dashboard)
- **Original Stories:** `docs/stories/2.3-2.10-*.md`

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2024-11-04 | 1.0 | Initial epic creation (Epic 0.3 of 3-epic structure) | John (PM) |

---

## Notes

- **Final Epic:** Completes the entire shadcn/ui migration
- **Can Parallelize:** After Epic 0.1, can run parallel with Epic 0.2
- **Complex Forms:** More complex than Epic 0.2 due to business rules
- **Cleanup Critical:** Story 0.3.4 ensures no legacy remnants
- **Success Marks Complete Migration:** When this epic is Done, entire app is shadcn/ui
