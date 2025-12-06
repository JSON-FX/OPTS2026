# Epic 0.2: shadcn/ui Administrative Interfaces - Brownfield Enhancement

**Epic ID:** Epic-0.2
**Epic Type:** Brownfield Enhancement (UI Layer Migration - Phase 2)
**Priority:** High
**Status:** Ready
**Created:** 2024-11-04
**Dependencies:** Epic 0.1 (requires layouts and component library)
**Blocks:** None (can run parallel with Epic 0.3)

---

## Epic Goal

Migrate all administrative CRUD interfaces (User Management and 6 Repository types) to shadcn/ui DataTable and Form components, standardizing table features (sort, filter, paginate, select) and ensuring consistent admin UX across all repository management pages.

---

## Epic Description

### Existing System Context

**Current Relevant Functionality:**
- **User Management:** Admin-only interface at `/admin/users` for managing users, roles, and office assignments (Story 1.3)
- **6 Repository Types:** Offices, Suppliers, Particulars, Departments, FundTypes, Workflows (Stories 1.4-1.6)
- Each repository has Index (list), Create, Edit pages with basic tables and forms
- Current tables lack consistent sorting, filtering, pagination controls
- Forms use mixed custom components (TextInput, PrimaryButton, etc.)

**Technology Stack:**
- Already migrated: Layouts (Epic 0.1), shadcn component library installed
- Existing: Laravel RBAC middleware, Inertia form handling, Laravel validation

**Integration Points:**
- Inherits from AuthenticatedLayout (migrated in Epic 0.1)
- Uses Toast for flash messages (migrated in Epic 0.1)
- RBAC enforced: Only Administrators can access these pages
- Soft deletes and is_active toggles

### Enhancement Details

**What's Being Added/Changed:**

This epic **standardizes all administrative interfaces** by:

1. **User Management Migration:** Replace user list table with DataTable, forms with shadcn Form components, delete confirmations with AlertDialog
2. **Repository Management Migration:** Migrate all 6 repository CRUD interfaces to DataTable + Form pattern
3. **Consistent Table Features:** Every table gets sorting, filtering, search, pagination, column visibility
4. **Consistent Form Pattern:** All forms follow shadcn Form structure with proper validation display
5. **Status Controls:** Use Switch for is_active toggles, Badge for status displays

**How It Integrates:**
- **Layout Inheritance:** All pages use migrated AuthenticatedLayout from Epic 0.1
- **RBAC Preserved:** Middleware restrictions unchanged (Admin-only access)
- **Backend Unchanged:** Controllers, models, validation rules remain identical
- **Toast Notifications:** Success/error messages via global Toaster from Epic 0.1

**Success Criteria:**
- ✅ User Management uses DataTable with all features
- ✅ All 6 repository types use DataTable
- ✅ All forms use shadcn Form components
- ✅ is_active toggles use Switch component
- ✅ Delete confirmations use AlertDialog
- ✅ Status displays use Badge
- ✅ Zero console errors on admin pages
- ✅ RBAC still enforced (Viewer/Endorser get 403)
- ✅ All CRUD operations functional
- ✅ Visual regression tests pass

---

## Stories

### **Story 0.2.1: Admin User Management Migration**

**Description:** Migrate User Management pages (Index, Create, Edit) to shadcn DataTable and Form components, updating Story 1.3 implementation.

**Key Tasks:**
- **Index.tsx (User List):**
  - Replace custom table → shadcn DataTable
  - Add DataTable toolbar with search Input
  - Add Select filters for role, office
  - Implement column sorting (name, email, role, created_at)
  - Add row selection with checkboxes
  - Add actions DropdownMenu per row (Edit, Delete)
  - Display role with Badge component
  - Add pagination controls
  - Add column visibility toggle
- **Create.tsx:**
  - Replace all inputs → shadcn Form components
  - Use Input for name, email, password fields
  - Use Select for role dropdown
  - Use Select or Checkbox for office assignment
  - Use FormMessage for validation errors
  - Replace PrimaryButton → Button
- **Edit.tsx:**
  - Same as Create but pre-populate data
  - Make password optional (only update if provided)
- **Delete Confirmation:**
  - Replace custom modal → AlertDialog
  - Show warning if user has audit trail
- Test RBAC (only Admin can access)
- Verify Laravel validation errors display via FormMessage

**Acceptance Criteria:**
- [ ] User list uses DataTable with search, sort, filter, pagination
- [ ] Role filter uses Select dropdown
- [ ] Office filter uses Select dropdown
- [ ] Role displays as Badge
- [ ] Create/Edit forms use shadcn Form components
- [ ] Password field has show/hide toggle
- [ ] Delete uses AlertDialog confirmation
- [ ] RBAC enforced (Viewer/Endorser get 403)
- [ ] All CRUD operations functional
- [ ] Zero console errors
- [ ] Playwright tests pass (RBAC, CRUD, validation)
- [ ] Visual regression tests pass

**Testing:**
- Playwright: Test admin user can access /admin/users
- Playwright: Test viewer/endorser get 403
- Playwright: Test create user with validation errors
- Playwright: Test edit user
- Playwright: Test delete user with confirmation
- Playwright: Screenshot at mobile/tablet/desktop

---

### **Story 0.2.2: Repository Management Migration**

**Description:** Migrate all 6 repository CRUD interfaces (Offices, Suppliers, Particulars, Departments, FundTypes, Workflows) to standardized DataTable and Form pattern.

**Key Tasks:**

**For Each Repository (6 total):**

1. **Offices Repository:**
   - Index: DataTable with search, sort by name/abbreviation, filter by is_active
   - is_active toggle: Switch component
   - Create/Edit: Form with Input (name, abbreviation), Switch (is_active)
   - Delete: AlertDialog with FK constraint check

2. **Suppliers Repository:**
   - Index: DataTable with search, sort, filter by is_active
   - Create/Edit: Form with Input (name, contact_person, contact_number), Textarea (address), Switch (is_active)
   - Textarea for address field

3. **Particulars Repository:**
   - Index: DataTable with search, sort, filter by is_active
   - Create/Edit: Form with Input (name, code), Textarea (description), Switch (is_active)

4. **Departments Repository:**
   - Index: DataTable with search, sort, filter by is_active
   - Create/Edit: Form with Input (name, abbreviation), Switch (is_active)

5. **FundTypes Repository:**
   - Index: DataTable with search, sort, filter by is_active
   - Create/Edit: Form with Input (name, code), Textarea (description), Switch (is_active)
   - Delete: Check FK constraint (referenced by PRs)

6. **Workflows Repository:**
   - Index: DataTable with search, sort by name, filter by category
   - Create/Edit: Form with Input (name), Select (category: PR/PO/VCH), Textarea (description)
   - Display category as Badge

**Shared Components:**
- All tables: DataTable with toolbar (search Input, filter Selects)
- All forms: shadcn Form with proper FormItem/FormLabel/FormControl/FormMessage structure
- All deletes: AlertDialog confirmation
- Status displays: Badge component
- is_active toggles: Switch component

**Navigation:**
- Consider Tabs component for switching between repositories
- Or keep separate routes with consistent navigation pattern

**Acceptance Criteria:**
- [ ] All 6 repositories use DataTable
- [ ] All tables have search, sort, filter, pagination
- [ ] All forms use shadcn Form components
- [ ] is_active fields use Switch
- [ ] Address/description fields use Textarea
- [ ] Category displays use Badge
- [ ] Delete confirmations use AlertDialog
- [ ] FK constraint violations display proper errors
- [ ] All CRUD operations functional per repository
- [ ] Visual consistency across all repositories
- [ ] Zero console errors
- [ ] Playwright tests pass for all repositories
- [ ] Responsive design at all breakpoints

**Testing:**
- Playwright: Test each repository CRUD operations
- Playwright: Test is_active toggle updates record
- Playwright: Test delete with FK constraint (FundType, Office)
- Playwright: Test search/filter functionality
- Playwright: Screenshot each repository at mobile/tablet/desktop
- Manual: Verify soft deletes work correctly

---

## Compatibility Requirements

- ✅ **Existing APIs Unchanged:** All controller endpoints identical
- ✅ **Database Schema Unchanged:** No migration changes
- ✅ **RBAC Preserved:** Admin-only access still enforced
- ✅ **Soft Deletes Work:** Deletion logic unchanged
- ✅ **FK Constraints:** Deletion prevention logic intact
- ✅ **Validation Rules:** Laravel validation unchanged

---

## Risk Mitigation

### Primary Risk: DataTable Performance with Large Datasets

**Description:** If repositories have 1000+ records, DataTable rendering could be slow.

**Mitigation Strategy:**
1. **Server-side Pagination:** DataTable uses Laravel paginator (50 per page)
2. **Eager Loading:** Ensure N+1 queries prevented in controllers
3. **Client-side Sorting:** Only sort current page data
4. **Virtual Scrolling:** Consider if datasets exceed 100 records per page

**Rollback Plan:**
- If performance issues, revert to simple table with pagination
- DataTable can be added incrementally (start with smallest repositories)

### Secondary Risk: Form Validation Display Breaks

**Description:** Laravel validation errors might not map correctly to FormMessage.

**Mitigation Strategy:**
1. Test validation with all field types (Input, Select, Textarea, Switch)
2. Verify Inertia error prop structure matches FormMessage expectations
3. Add manual tests for validation on each form
4. Document validation pattern for future forms

---

## Definition of Done

- ✅ All 2 stories completed with acceptance criteria met
- ✅ User Management fully migrated (DataTable + Forms)
- ✅ All 6 repositories fully migrated (DataTable + Forms)
- ✅ Visual consistency across all admin interfaces
- ✅ All CRUD operations functional and tested
- ✅ RBAC enforced on all admin routes
- ✅ Zero console errors on all admin pages
- ✅ Playwright tests pass for all pages
- ✅ Visual regression tests pass
- ✅ Documentation updated (component mapping, admin patterns)

---

## Dependencies

**Requires:** Epic 0.1 complete (layouts, component library)

**Blocks:** None (independent of Epic 0.3)

---

## Timeline Estimate

- **Total Stories:** 2
- **Estimated Duration:** 1 week (40 hours)
- **Story Breakdown:**
  - Story 0.2.1: 2 days (16 hours) - User Management (1 interface, 3 pages)
  - Story 0.2.2: 3 days (24 hours) - 6 Repositories (18 pages total)

**Note:** Story 0.2.2 is larger but follows repetitive pattern, making implementation faster after first repository.

---

## Related Documentation

- **Architecture:** `docs/architecture-shadcn-migration.md` (Phase 3)
- **Checklist:** `docs/shadcn-migration-checklist.md` (Phase 5)
- **Story Updates:** `docs/shadcn-story-updates.md` (Stories 1.3-1.6)
- **Original Stories:** `docs/stories/1.3-rbac-user-management.md`, `docs/stories/1.4-1.6-*.md`

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2024-11-04 | 1.0 | Initial epic creation (Epic 0.2 of 3-epic structure) | John (PM) |

---

## Notes

- **Repetitive Pattern:** After migrating first repository, pattern can be copy-pasted to others
- **Can Parallelize:** After Epic 0.1 completes, this can run parallel with Epic 0.3
- **Admin Focus:** All pages Admin-only, lower testing complexity (no role variations)
- **DataTable Consistency:** Establishes pattern for Epic 0.3 procurement tables
