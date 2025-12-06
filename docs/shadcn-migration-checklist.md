# 📋 COMPREHENSIVE SHADCN/UI MIGRATION CHECKLIST

## Phase 1: Pre-Migration Setup & Validation ✅

### 1.1 Environment Verification
- [ ] **Verify Laravel Version:** Confirm Laravel 10/11 installed
- [ ] **Check React Version:** Ensure React 18.2.0+ is installed
- [ ] **Validate TypeScript:** Confirm TypeScript 5.0.2+ configured
- [ ] **Test Inertia.js:** Verify Inertia 2.0.0+ working
- [ ] **Confirm Tailwind CSS:** Check version 3.2.1+
- [ ] **Validate Vite:** Ensure Vite 7.0.7+ configured

### 1.2 MCP Tool Setup
- [ ] **Install MCP Configuration:**
  ```bash
  pnpm dlx shadcn@latest mcp init --client claude
  ```
- [ ] **Verify .mcp.json exists** with correct configuration
- [ ] **Test MCP Connection:**
  ```typescript
  mcp__shadcn__get_project_registries()
  ```
- [ ] **Confirm Registry Access:**
  ```typescript
  mcp__shadcn__list_items_in_registries({ registries: ["@shadcn"], limit: 5 })
  ```

### 1.3 Project Configuration
- [ ] **Verify components.json** exists with correct paths:
  - `resources/js/components/ui/` as target directory
  - `@/components` alias configured
  - new-york style selected
- [ ] **Check tsconfig.json** paths:
  ```json
  "@/*": ["./resources/js/*"],
  "@/components/*": ["./resources/js/components/*"]
  ```
- [ ] **Validate vite.config.js** aliases match tsconfig

### 1.4 Baseline Creation
- [ ] **Git Commit Current State:**
  ```bash
  git add . && git commit -m "Pre-shadcn migration baseline"
  ```
- [ ] **Create Component Inventory:**
  ```bash
  find resources/js/Components -type f -name "*.tsx" > component-inventory.txt
  ```
- [ ] **Screenshot All Pages** using Playwright MCP:
  ```typescript
  // For each major page
  await mcp__playwright__browser_navigate({ url: page })
  await mcp__playwright__browser_take_screenshot({ path: `baseline/${page}.png` })
  ```
- [ ] **Document Console State:**
  ```typescript
  const logs = await mcp__playwright__browser_console_messages()
  // Save any existing errors/warnings
  ```

---

## Phase 2: Core Component Installation 🔧

### 2.1 Fetch Essential Components via MCP
- [ ] **Get Component List:**
  ```typescript
  const essentials = await mcp__shadcn__get_add_command_for_items({
    items: [
      "@shadcn/button",
      "@shadcn/input",
      "@shadcn/label",
      "@shadcn/form",
      "@shadcn/card",
      "@shadcn/toast",
      "@shadcn/toaster",
      "@shadcn/alert",
      "@shadcn/badge",
      "@shadcn/dialog"
    ]
  })
  ```
- [ ] **Execute Installation Command** returned by MCP
- [ ] **Verify lib/utils.ts** created with cn() helper
- [ ] **Check CSS Variables** in resources/css/app.css

### 2.2 Test Each Core Component
- [ ] **Button Component:**
  - [ ] Create test page with all variants
  - [ ] Test with Playwright MCP
  - [ ] Verify no console errors
  - [ ] Check responsive behavior
- [ ] **Input Component:**
  - [ ] Test with Inertia useForm()
  - [ ] Verify validation display
  - [ ] Check disabled states
- [ ] **Form Component:**
  - [ ] Integration with React Hook Form
  - [ ] Laravel validation error display
  - [ ] CSRF token handling
- [ ] **Toast Component:**
  - [ ] Flash message integration
  - [ ] Auto-dismiss functionality
  - [ ] Position variants

### 2.3 Component Mapping Documentation
- [ ] **Create Migration Map:**
  ```typescript
  const componentMap = {
    'TextInput': 'Input',
    'PrimaryButton': 'Button',
    'InputLabel': 'Label',
    'InputError': 'FormMessage',
    // ... document all mappings
  }
  ```
- [ ] **Document Prop Changes** for each component
- [ ] **Create Import Update Script**

---

## Phase 3: DataTable Implementation 📊

### 3.1 Fetch DataTable Components
- [ ] **Get DataTable Package:**
  ```typescript
  await mcp__shadcn__search_items_in_registries({
    registries: ["@shadcn"],
    query: "data-table"
  })
  ```
- [ ] **View Examples:**
  ```typescript
  await mcp__shadcn__get_item_examples_from_registries({
    registries: ["@shadcn"],
    query: "data-table-demo"
  })
  ```
- [ ] **Install TanStack Table:** Verify @tanstack/react-table installed

### 3.2 DataTable Features Implementation
- [ ] **Column Definitions:**
  - [ ] Selection column with checkbox
  - [ ] Sortable headers
  - [ ] Actions dropdown
  - [ ] Status badges
  - [ ] Currency formatting
- [ ] **Toolbar Implementation:**
  - [ ] Search input
  - [ ] Filter dropdowns
  - [ ] Column visibility toggle
  - [ ] Export button placeholder
- [ ] **Pagination:**
  - [ ] Laravel paginator adapter
  - [ ] Page size selector
  - [ ] Page info display
- [ ] **Row Features:**
  - [ ] Row selection
  - [ ] Hover states
  - [ ] Click handlers
  - [ ] Responsive overflow

### 3.3 DataTable Testing
- [ ] **Test with Real Data:**
  ```typescript
  // Test with procurement data
  const table = <DataTable data={procurements} columns={columns} />
  ```
- [ ] **Performance Testing:**
  - [ ] 100+ rows rendering
  - [ ] Sort performance
  - [ ] Filter responsiveness
- [ ] **Mobile Testing:**
  - [ ] Horizontal scroll
  - [ ] Touch interactions
  - [ ] Responsive columns

---

## Phase 4: Layout Migration 🎨

### 4.1 Fetch Layout Components
- [ ] **Navigation Components:**
  ```typescript
  await mcp__shadcn__get_add_command_for_items({
    items: ["@shadcn/navigation-menu", "@shadcn/sheet", "@shadcn/separator"]
  })
  ```
- [ ] **Layout Helpers:**
  ```typescript
  await mcp__shadcn__get_add_command_for_items({
    items: ["@shadcn/scroll-area", "@shadcn/skeleton", "@shadcn/avatar"]
  })
  ```

### 4.2 AuthenticatedLayout Migration
- [ ] **Create New Layout:** `resources/js/components/layouts/authenticated-layout.tsx`
- [ ] **Implement Navigation:**
  - [ ] Desktop navigation menu
  - [ ] Mobile sheet menu
  - [ ] User dropdown
  - [ ] Notification bell
- [ ] **Add Global Toaster:**
  ```typescript
  <Toaster />
  ```
- [ ] **Test All Pages** using this layout
- [ ] **Verify Props Passing** from Inertia

### 4.3 GuestLayout Migration
- [ ] **Create Guest Layout:** `resources/js/components/layouts/guest-layout.tsx`
- [ ] **Style Login/Register Pages:**
  - [ ] Card-based layout
  - [ ] Centered content
  - [ ] Logo placement
- [ ] **Test Auth Flows:**
  - [ ] Login form
  - [ ] Registration
  - [ ] Password reset

---

## Phase 5: Page-by-Page Migration 📄

### 5.1 Migration Order
- [ ] **Priority 1 - Auth Pages:**
  - [ ] Login.tsx
  - [ ] Register.tsx
  - [ ] ForgotPassword.tsx
  - [ ] ResetPassword.tsx
- [ ] **Priority 2 - Dashboard:**
  - [ ] Dashboard.tsx
  - [ ] Stats components
  - [ ] Recent activity
- [ ] **Priority 3 - CRUD Pages:**
  - [ ] PurchaseRequests/Index.tsx
  - [ ] PurchaseRequests/Create.tsx
  - [ ] PurchaseRequests/Edit.tsx
- [ ] **Priority 4 - Admin Pages:**
  - [ ] Admin/Users/Index.tsx
  - [ ] Admin/Repositories/*.tsx

### 5.2 For Each Page Migration
- [ ] **Update Imports:**
  ```typescript
  // Before
  import TextInput from '@/Components/TextInput'
  // After
  import { Input } from '@/components/ui/input'
  ```
- [ ] **Replace Components** using mapping guide
- [ ] **Test with Playwright:**
  ```typescript
  await mcp__playwright__browser_navigate({ url: pagePath })
  const errors = await mcp__playwright__browser_console_messages()
  assert(errors.filter(e => e.level === 'error').length === 0)
  ```
- [ ] **Visual Regression:**
  ```typescript
  await mcp__playwright__browser_take_screenshot({ path: `after/${page}.png` })
  // Compare with baseline
  ```
- [ ] **Test Interactions:**
  - [ ] Form submissions
  - [ ] Button clicks
  - [ ] Navigation
  - [ ] Modals/Dialogs

---

## Phase 6: Testing & Validation ✅

### 6.1 Component Testing
- [ ] **Unit Tests:**
  - [ ] Update existing tests for new components
  - [ ] Add tests for composite components
  - [ ] Test prop interfaces
- [ ] **Integration Tests:**
  - [ ] Inertia page tests
  - [ ] Form submission tests
  - [ ] Navigation tests

### 6.2 E2E Testing with Playwright MCP
- [ ] **Full User Flows:**
  - [ ] Login → Dashboard → Create PR → Logout
  - [ ] Admin user management flow
  - [ ] Repository CRUD operations
- [ ] **Responsive Testing:**
  ```typescript
  const viewports = [
    { width: 375, height: 812 },  // Mobile
    { width: 768, height: 1024 }, // Tablet
    { width: 1920, height: 1080 } // Desktop
  ]
  for (const viewport of viewports) {
    await mcp__playwright__browser_resize(viewport)
    // Test at each size
  }
  ```
- [ ] **Performance Metrics:**
  ```typescript
  // Measure load times
  const metrics = await mcp__playwright__browser_evaluate({
    script: 'performance.getEntriesByType("navigation")[0]'
  })
  ```

### 6.3 Accessibility Testing
- [ ] **ARIA Labels:** Verify all interactive elements
- [ ] **Keyboard Navigation:** Tab through all forms
- [ ] **Screen Reader:** Test with NVDA/JAWS
- [ ] **Color Contrast:** Verify WCAG AA compliance

---

## Phase 7: Cleanup & Optimization 🧹

### 7.1 Remove Legacy Components
- [ ] **Delete Old Components:**
  ```bash
  rm -rf resources/js/Components/*.tsx
  rm -rf resources/js/Layouts/*.tsx
  ```
- [ ] **Update .gitignore** if needed
- [ ] **Remove Unused Dependencies:**
  ```bash
  npm prune
  ```

### 7.2 Performance Optimization
- [ ] **Bundle Analysis:**
  ```bash
  npm run build -- --analyze
  ```
- [ ] **Tree Shaking:** Verify unused code eliminated
- [ ] **Lazy Loading:** Implement for large components
- [ ] **CSS Optimization:** Remove unused Tailwind classes

### 7.3 Documentation
- [ ] **Update README.md** with new component structure
- [ ] **Create Component Guide** with examples
- [ ] **Document Patterns:**
  - [ ] Form handling pattern
  - [ ] DataTable usage
  - [ ] Toast notifications
  - [ ] Modal/Dialog pattern
- [ ] **Update Storybook** (if applicable)

---

## Phase 8: Final Validation & Deployment 🚀

### 8.1 Quality Assurance
- [ ] **Run Audit Checklist:**
  ```typescript
  const audit = await mcp__shadcn__get_audit_checklist()
  // Complete all items
  ```
- [ ] **Zero Console Errors** across all pages
- [ ] **All Tests Passing:**
  ```bash
  npm run test
  php artisan test
  ```
- [ ] **Lighthouse Scores:**
  - [ ] Performance > 90
  - [ ] Accessibility > 95
  - [ ] Best Practices > 95

### 8.2 Deployment Preparation
- [ ] **Create Deployment Branch:**
  ```bash
  git checkout -b shadcn-migration-complete
  ```
- [ ] **Update Changelog** with migration details
- [ ] **Prepare Rollback Plan:**
  ```bash
  git tag pre-shadcn-deployment
  ```
- [ ] **Stage Deployment:**
  - [ ] Deploy to staging
  - [ ] Run smoke tests
  - [ ] Get stakeholder approval

### 8.3 Production Deployment
- [ ] **Deploy with Monitoring:**
  - [ ] Watch error rates
  - [ ] Monitor performance metrics
  - [ ] Check user feedback
- [ ] **Post-Deployment Tests:**
  - [ ] Critical path testing
  - [ ] Load testing
  - [ ] Security scan
- [ ] **Documentation:**
  - [ ] Update production docs
  - [ ] Notify team of changes
  - [ ] Archive migration artifacts

---

## 📊 Migration Metrics & Success Criteria

### Quantitative Metrics
- [ ] **Component Coverage:** 100% of UI components migrated
- [ ] **Import Consistency:** Zero mixed imports (old + new)
- [ ] **Console Errors:** 0 errors across all pages
- [ ] **Test Coverage:** >80% for new components
- [ ] **Bundle Size:** <10% increase from baseline
- [ ] **Load Time:** <3 seconds P95
- [ ] **Accessibility Score:** WCAG AA compliant

### Qualitative Metrics
- [ ] **Developer Experience:** Improved component discovery
- [ ] **Code Consistency:** Single component system
- [ ] **Maintainability:** Reduced custom CSS
- [ ] **User Experience:** Consistent UI patterns
- [ ] **Documentation:** Complete and current

---

## 🔧 Troubleshooting Guide

### Common Issues & Solutions

**MCP Tool Not Working:**
```bash
# Restart Claude
# Check .mcp.json
cat .mcp.json
# Test with simple command
mcp__shadcn__get_project_registries()
```

**Import Errors After Migration:**
```typescript
// Check tsconfig.json paths
// Verify vite.config.js aliases
// Ensure lowercase 'components' folder
```

**Style Conflicts:**
```css
/* Check CSS variable conflicts in app.css */
/* Verify Tailwind config compatibility */
/* Check for !important overrides */
```

**Playwright Test Failures:**
```typescript
// Increase timeouts for slow components
// Check for timing issues with async operations
// Verify correct selectors
```

---

## 📝 Notes & Resources

### MCP Commands Reference
```typescript
// List all available components
mcp__shadcn__list_items_in_registries({ registries: ["@shadcn"] })

// Search for specific components
mcp__shadcn__search_items_in_registries({
  registries: ["@shadcn"],
  query: "form"
})

// Get component details
mcp__shadcn__view_items_in_registries({
  items: ["@shadcn/button"]
})

// Get usage examples
mcp__shadcn__get_item_examples_from_registries({
  registries: ["@shadcn"],
  query: "button-demo"
})

// Get installation command
mcp__shadcn__get_add_command_for_items({
  items: ["@shadcn/button", "@shadcn/input"]
})

// Run audit
mcp__shadcn__get_audit_checklist()
```

### Playwright MCP Commands
```typescript
// Navigate to page
mcp__playwright__browser_navigate({ url: "/dashboard" })

// Take screenshot
mcp__playwright__browser_take_screenshot({
  path: "screenshots/page.png"
})

// Check console
mcp__playwright__browser_console_messages()

// Test interaction
mcp__playwright__browser_click({ selector: "button[type='submit']" })

// Resize viewport
mcp__playwright__browser_resize({ width: 375, height: 812 })

// Fill form
mcp__playwright__browser_fill_form({
  selector: "form",
  data: { email: "test@example.com" }
})
```

### Component Mapping Reference
| Old Component | New Component | Notes |
|--------------|---------------|-------|
| TextInput | Input | Use with FormControl |
| PrimaryButton | Button | variant="default" |
| SecondaryButton | Button | variant="secondary" |
| DangerButton | Button | variant="destructive" |
| InputLabel | Label | Use with FormLabel |
| InputError | FormMessage | Inside FormItem |
| Modal | Dialog | Different prop structure |
| Dropdown | DropdownMenu | More features |
| ResponsiveNavLink | NavigationMenu | Built-in responsive |

### Laravel/Inertia Adaptations
```typescript
// Inertia form with shadcn
const { data, setData, post, errors } = useForm({ /* ... */ })

// Flash to Toast
const { flash } = usePage().props
useEffect(() => {
  if (flash?.message) {
    toast({ description: flash.message })
  }
}, [flash])

// Laravel pagination with DataTable
<DataTable
  data={props.items.data}
  pageCount={props.items.last_page}
  currentPage={props.items.current_page}
/>
```

---

## ✅ Checklist Summary

**Total Phases:** 8
**Total Checkable Items:** 150+
**Estimated Timeline:** 4 weeks
**Risk Level:** Low (with systematic approach)

**Key Success Factors:**
1. MCP tool for consistent component fetching
2. Playwright testing at every step
3. Git checkpoints for rollback capability
4. Phase-by-phase approach minimizes risk
5. Comprehensive documentation throughout

---

*Last Updated: November 2024*
*Architecture Document: `docs/architecture-shadcn-migration.md`*
*Related Docs: `docs/architecture.md` (main project)*