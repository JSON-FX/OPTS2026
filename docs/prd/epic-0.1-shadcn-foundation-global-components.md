# Epic 0.1: shadcn/ui Foundation & Global Components - Brownfield Enhancement

**Epic ID:** Epic-0.1
**Epic Type:** Brownfield Enhancement (UI Layer Migration - Phase 1)
**Priority:** High
**Status:** Ready
**Created:** 2024-11-04
**Dependencies:** None
**Blocks:** Epic 0.2, Epic 0.3

---

## Epic Goal

Establish the shadcn/ui component foundation, migrate global layouts and navigation, and update all authentication pages to standardized shadcn components, creating the baseline for all subsequent UI migrations with zero functionality regression.

---

## Epic Description

### Existing System Context

**Current Relevant Functionality:**
- OPTS2026 has two main layouts: `AuthenticatedLayout.tsx` (with navigation, user menu) and `GuestLayout.tsx` (for login/register)
- Authentication pages: Login, Register, ForgotPassword, ResetPassword using mixed custom components
- Partial shadcn/ui implementation: 11 components exist, but legacy components still in use
- Global navigation uses custom `ResponsiveNavLink` and `Dropdown` components

**Technology Stack:**
- React 18.2 + TypeScript 5.0 + Inertia.js 2.0
- Tailwind CSS 3.2 + Vite 7
- Existing shadcn/ui setup: components.json configured, Radix UI dependencies installed

**Integration Points:**
- All pages inherit from AuthenticatedLayout or GuestLayout
- Laravel flash messages need Toast integration
- Inertia auth state management (props.auth.user)
- RBAC navigation filtering

### Enhancement Details

**What's Being Added/Changed:**

This epic establishes the **foundation** for the entire shadcn/ui migration by:

1. **Component Installation:** Fetch all missing shadcn/ui components via MCP tool
2. **Layout Migration:** Replace AuthenticatedLayout and GuestLayout with shadcn-based implementations
3. **Navigation System:** Implement NavigationMenu (desktop) + Sheet (mobile) with responsive behavior
4. **Global Toaster:** Integrate Toast system for Laravel flash messages
5. **Authentication UI:** Migrate all auth pages to shadcn Form components

**How It Integrates:**
- **Foundation Layer:** All subsequent pages will inherit these migrated layouts
- **Zero Backend Changes:** Laravel controllers, auth logic, Inertia responses unchanged
- **Testing Baseline:** Playwright captures screenshots before/after for visual regression
- **Component Library Complete:** All required shadcn components available for Epic 0.2 and 0.3

**Success Criteria:**
- ✅ All required shadcn components installed via MCP
- ✅ Layouts migrated (AuthenticatedLayout, GuestLayout)
- ✅ Navigation responsive at mobile/tablet/desktop
- ✅ Global Toaster displays Laravel flash messages
- ✅ All auth pages use shadcn Form components
- ✅ Zero console errors in layouts and auth pages
- ✅ Visual regression tests pass
- ✅ Authentication flows functional (login, register, password reset)

---

## Stories

### **Story 0.1.1: Pre-Migration Setup & Component Installation**

**Description:** Verify environment, set up MCP shadcn tool, fetch all required shadcn/ui components, create baseline screenshots, and document component mapping.

**Key Tasks:**
- Verify MCP shadcn tool connection (`mcp__shadcn__get_project_registries()`)
- List all required components from `shadcn-story-updates.md`
- Fetch core components: Button, Input, Form, Label, Select, Textarea, Checkbox, Badge, Card, Separator, Toast, AlertDialog, Dialog, Tooltip, Switch, Tabs, Accordion
- Fetch DataTable components and examples
- Install @tanstack/react-table if not present
- Create Playwright baseline screenshots for all 30+ existing pages
- Document component mapping table (TextInput → Input, PrimaryButton → Button, etc.)
- Verify lib/utils.ts has cn() helper function
- Configure CSS variables in app.css

**Acceptance Criteria:**
- [ ] MCP shadcn tool verified working
- [ ] All core components installed in `resources/js/components/ui/`
- [ ] DataTable components fetched and documented
- [ ] Baseline screenshots captured for all pages
- [ ] Component mapping document created at `docs/shadcn-component-mapping.md`
- [ ] lib/utils.ts configured with cn() helper
- [ ] CSS variables properly set in resources/css/app.css
- [ ] TypeScript compilation successful with new components

**Testing:**
- Verify `npx tsc` compiles without errors
- Verify `npm run dev` starts without errors
- Test cn() utility function works

---

### **Story 0.1.2: Global Layout & Navigation Migration**

**Description:** Migrate AuthenticatedLayout and GuestLayout to shadcn/ui components, implementing responsive navigation, user dropdown menu, and global Toaster.

**Key Tasks:**
- **AuthenticatedLayout:**
  - Replace custom navigation with NavigationMenu (desktop)
  - Replace custom mobile menu with Sheet component
  - Replace Dropdown with DropdownMenu for user menu
  - Add Avatar component for user display
  - Add Separator for menu sections
  - Integrate global Toaster component
  - Configure Toaster to display Laravel flash messages
- **GuestLayout:**
  - Wrap auth forms in Card components
  - Update styling for centered layout
  - Add consistent branding/logo placement
- Test navigation at all breakpoints (375px, 768px, 1920px)
- Verify Inertia props.auth.user passes correctly
- Test RBAC navigation filtering still works

**Acceptance Criteria:**
- [ ] AuthenticatedLayout uses NavigationMenu for desktop
- [ ] Mobile navigation uses Sheet (hamburger menu)
- [ ] User dropdown uses DropdownMenu + Avatar
- [ ] Global Toaster integrated and displays flash messages
- [ ] GuestLayout uses Card for auth form containers
- [ ] Navigation responsive at all breakpoints
- [ ] RBAC filtering still enforced (Viewer/Endorser/Admin see correct links)
- [ ] Zero console errors in layouts
- [ ] Keyboard navigation accessible (Tab, Enter, Escape work)
- [ ] Playwright tests pass for layout rendering

**Testing:**
- Playwright: Navigate to dashboard, check console logs
- Playwright: Screenshot at mobile/tablet/desktop viewports
- Playwright: Test navigation clicks work
- Manual: Verify flash message → Toast displays correctly

---

### **Story 0.1.3: Authentication Pages Migration**

**Description:** Migrate Login, Register, ForgotPassword, and ResetPassword pages to use shadcn Form components with proper validation display and error handling.

**Key Tasks:**
- **Login.tsx:**
  - Replace TextInput → Input for email/password
  - Replace InputLabel → Label
  - Replace PrimaryButton → Button
  - Replace InputError → FormMessage (within FormItem structure)
  - Add Card wrapper (inherited from GuestLayout)
  - Test login flow with valid/invalid credentials
- **Register.tsx:**
  - Replace all inputs with shadcn Form components
  - Add Checkbox for terms acceptance
  - Test registration flow with validation errors
- **ForgotPassword.tsx:**
  - Replace email input → Input
  - Test password reset request flow
- **ResetPassword.tsx:**
  - Replace password inputs → Input (type="password")
  - Test password reset completion flow
- Update Inertia useForm() integration with shadcn Form
- Ensure Laravel validation errors display via FormMessage

**Acceptance Criteria:**
- [ ] All auth pages use shadcn Input, Label, Button, FormMessage
- [ ] Validation errors display correctly via FormMessage
- [ ] Forms submit successfully via Inertia
- [ ] Visual consistency across all auth pages
- [ ] Responsive design at mobile/tablet/desktop
- [ ] Password visibility toggle works
- [ ] Remember me checkbox works (Login)
- [ ] Terms checkbox works (Register)
- [ ] Zero console errors on auth pages
- [ ] Playwright tests pass for all auth flows

**Testing:**
- Playwright: Test login with valid credentials
- Playwright: Test login with invalid credentials (check error display)
- Playwright: Test registration with validation errors
- Playwright: Test password reset flow
- Playwright: Screenshot all auth pages at all viewports

---

## Compatibility Requirements

- ✅ **Existing Auth Logic Unchanged:** Laravel Breeze authentication intact
- ✅ **Inertia Props Compatible:** props.auth.user, props.flash work unchanged
- ✅ **RBAC Navigation Preserved:** Role-based link filtering still enforced
- ✅ **Session Management:** Remember me, CSRF tokens still functional
- ✅ **Layout Inheritance:** All pages still extend from migrated layouts
- ✅ **Performance Neutral:** No degradation in page load times

---

## Risk Mitigation

### Primary Risk: Breaking Existing Pages That Use Layouts

**Description:** Since all pages inherit from AuthenticatedLayout/GuestLayout, changes could break every page in the application.

**Mitigation Strategy:**
1. **Baseline Screenshots:** Capture all 30+ pages before layout changes
2. **Prop Interface Preservation:** Maintain exact prop interfaces for layouts
3. **Incremental Testing:** Test each layout change immediately
4. **Git Checkpoint:** Tag `pre-epic-0.1` before starting
5. **Rollback Ready:** Can revert layouts independently if issues arise

**Rollback Plan:**
- Revert to tag `pre-epic-0.1` if critical breakage
- Layouts can be rolled back individually (guest vs authenticated)
- Keep old layout files as `.tsx.backup` during migration

### Secondary Risk: Flash Message Integration Breaks

**Description:** Toast integration with Laravel flash messages could fail silently.

**Mitigation Strategy:**
1. Test flash messages explicitly with Playwright
2. Add console logging during development
3. Verify success/error/info variants all work
4. Test with actual Laravel routes that flash messages

---

## Definition of Done

- ✅ All 3 stories completed with acceptance criteria met
- ✅ All shadcn components installed and documented
- ✅ Layouts migrated and responsive
- ✅ Authentication flows working (login, register, password reset)
- ✅ Global Toaster integrated and functional
- ✅ Zero console errors across layouts and auth pages
- ✅ Playwright baseline captured and regression tests pass
- ✅ Component mapping documented
- ✅ TypeScript compiles without errors
- ✅ All existing pages still render (inherit from new layouts)

---

## Dependencies

**Blocks:** Epic 0.2 and Epic 0.3 (they depend on layouts and component library being ready)

**Prerequisites:** None

---

## Timeline Estimate

- **Total Stories:** 3
- **Estimated Duration:** 2 weeks (80 hours)
- **Story Breakdown:**
  - Story 0.1.1: 1-2 days (16 hours) - Setup and installation
  - Story 0.1.2: 4-5 days (36 hours) - Layout migration (most critical)
  - Story 0.1.3: 3-4 days (28 hours) - Auth pages migration

---

## Related Documentation

- **Architecture:** `docs/architecture-shadcn-migration.md` (Phases 1-2)
- **Checklist:** `docs/shadcn-migration-checklist.md` (Phase 1-2)
- **Story Updates:** `docs/shadcn-story-updates.md` (Global sections)
- **Tech Stack:** `docs/architecture/tech-stack.md`

---

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2024-11-04 | 1.0 | Initial epic creation (Epic 0.1 of 3-epic structure) | John (PM) |

---

## Notes

- This is the **foundation epic** - most critical for entire migration
- Layouts affect **every page** - thorough testing essential
- Success here enables parallel work on Epic 0.2 and 0.3
- Consider this a **blocking epic** for the rest of the migration
