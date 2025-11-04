# 📋 SHADCN/UI STORY UPDATES GUIDE

## Overview
This document outlines the specific shadcn/ui component updates required for each existing story in the OPTS2026 project. Each story section includes:
- Current component usage
- Required shadcn/ui replacements
- MCP commands to fetch components
- Implementation notes

---

## Epic 1: Core System Foundation

### Story 1.1: Project Setup & Configuration
**Status**: Done
**UI Components**: Minimal (setup only)

**Required Actions**: None - this is infrastructure setup

---

### Story 1.2: Database Schema Foundation
**Status**: Done
**UI Components**: None (database only)

**Required Actions**: None - backend only story

---

### Story 1.3: RBAC Implementation with User Management
**Status**: Done
**Current Components**: Mixed (some shadcn already referenced)

**Components to Fetch via MCP**:
```typescript
// Fetch core components
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/table",      // For user list table
    "@shadcn/button",     // For action buttons
    "@shadcn/form",       // For create/edit forms
    "@shadcn/input",      // For text fields
    "@shadcn/select",     // For role dropdown
    "@shadcn/checkbox",   // For office multi-select
    "@shadcn/alert-dialog", // For delete confirmation
    "@shadcn/toast",      // For notifications
    "@shadcn/card",       // For form containers
    "@shadcn/badge",      // For role badges
    "@shadcn/separator"   // For visual separation
  ]
})

// Fetch DataTable for advanced features
await mcp__shadcn__search_items_in_registries({
  registries: ["@shadcn"],
  query: "data-table"
})
```

**Component Mapping**:
- `TextInput` → `Input`
- Custom table → `DataTable` with sorting, filtering, pagination
- Custom dropdowns → `Select`
- Custom alerts → `AlertDialog`

**File Updates Required**:
- `resources/js/Pages/Admin/Users/Index.tsx` - Replace with DataTable
- `resources/js/Pages/Admin/Users/Create.tsx` - Use Form components
- `resources/js/Pages/Admin/Users/Edit.tsx` - Use Form components

---

### Story 1.4: Office Repository Management
**Status**: Done
**Current Components**: Basic CRUD forms and tables

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/data-table",
    "@shadcn/switch",     // For is_active toggle
    "@shadcn/dialog",     // For create/edit modals
    "@shadcn/form",
    "@shadcn/input",
    "@shadcn/button",
    "@shadcn/toast"
  ]
})
```

**Component Mapping**:
- Basic table → `DataTable` with search, sort, filter by active status
- Checkbox for is_active → `Switch`
- Create/Edit forms → `Dialog` with `Form` components

**File Updates Required**:
- `resources/js/Pages/Admin/Repositories/Offices/Index.tsx`
- `resources/js/Pages/Admin/Repositories/Offices/Create.tsx`
- `resources/js/Pages/Admin/Repositories/Offices/Edit.tsx`

---

### Story 1.5: Supplier Repository Management
**Status**: Done
**Current Components**: CRUD forms and tables

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/data-table",
    "@shadcn/textarea",   // For address field
    "@shadcn/form",
    "@shadcn/input",
    "@shadcn/button",
    "@shadcn/switch",
    "@shadcn/dialog",
    "@shadcn/toast"
  ]
})
```

**Component Mapping**:
- Address input → `Textarea`
- Basic table → `DataTable` with full features
- Forms → `Dialog` with `Form` components

**File Updates Required**:
- `resources/js/Pages/Admin/Repositories/Suppliers/Index.tsx`
- `resources/js/Pages/Admin/Repositories/Suppliers/Create.tsx`
- `resources/js/Pages/Admin/Repositories/Suppliers/Edit.tsx`

---

### Story 1.6: Additional Repository Management
**Status**: Done
**Current Components**: Multiple CRUD interfaces

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/tabs",       // For switching between repositories
    "@shadcn/data-table",
    "@shadcn/form",
    "@shadcn/input",
    "@shadcn/select",
    "@shadcn/textarea",
    "@shadcn/switch",
    "@shadcn/button",
    "@shadcn/dialog",
    "@shadcn/toast",
    "@shadcn/badge"       // For status indicators
  ]
})
```

**Component Mapping**:
- Repository navigation → `Tabs`
- All tables → `DataTable`
- Status indicators → `Badge`

**File Updates Required**:
- `resources/js/Pages/Admin/Repositories/Index.tsx` (main page with tabs)
- All sub-repository pages for Particulars, Departments, FundTypes, Workflows

---

### Story 1.7: Navigation, Layout & Access Control
**Status**: Done
**Current Components**: Custom layouts and navigation

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/navigation-menu",  // Desktop nav
    "@shadcn/sheet",            // Mobile menu
    "@shadcn/dropdown-menu",    // User menu
    "@shadcn/avatar",           // User avatar
    "@shadcn/separator",        // Menu separators
    "@shadcn/scroll-area",      // For scrollable menus
    "@shadcn/skeleton",         // Loading states
    "@shadcn/button"
  ]
})
```

**Component Mapping**:
- `ResponsiveNavLink` → `NavigationMenu` + `Sheet` (mobile)
- `Dropdown` → `DropdownMenu`
- Custom user menu → `DropdownMenu` with `Avatar`

**Layout Files to Update**:
- `resources/js/Layouts/AuthenticatedLayout.tsx`
- `resources/js/Layouts/GuestLayout.tsx`
- `resources/js/Components/Navigation.tsx`

---

## Epic 2: Procurement & Transaction Lifecycle

### Story 2.1: Database Schema for Procurements & Transactions
**Status**: Done
**UI Components**: None (database only)

**Required Actions**: None - backend only story

---

### Story 2.2: Reference Number Generation Service
**Status**: Done
**UI Components**: None (service only)

**Required Actions**: None - backend service only

---

### Story 2.3: Procurement CRUD Operations
**Status**: Done
**Current Components**: Already mentions shadcn Toast

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/data-table",
    "@shadcn/form",
    "@shadcn/input",
    "@shadcn/textarea",      // For purpose field
    "@shadcn/select",         // For dropdowns
    "@shadcn/date-picker",    // For date fields
    "@shadcn/button",
    "@shadcn/badge",          // For status badges
    "@shadcn/alert-dialog",   // For archive confirmation
    "@shadcn/toast",
    "@shadcn/card",
    "@shadcn/tooltip"         // For locked field explanations
  ]
})

// Get currency input example
await mcp__shadcn__get_item_examples_from_registries({
  registries: ["@shadcn"],
  query: "currency-input-demo"
})
```

**Component Mapping**:
- Table → `DataTable` with filters, sorting, pagination
- Status display → `Badge` with color variants
- Delete confirmation → `AlertDialog`
- Field explanations → `Tooltip`
- Currency input → Custom Input with formatting

**File Updates Required**:
- `resources/js/Pages/Procurements/Index.tsx`
- `resources/js/Pages/Procurements/Create.tsx`
- `resources/js/Pages/Procurements/Edit.tsx`
- `resources/js/Pages/Procurements/Show.tsx`

---

### Story 2.4: Transaction Dependencies & Business Rule Validation
**Status**: In Progress
**UI Components**: Minimal (mostly backend)

**Required Actions**: Ensure error display uses Toast component

---

### Story 2.5: Purchase Request (PR) Transaction Management
**Status**: Done
**Current Components**: Forms and detail views

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/form",
    "@shadcn/select",         // Fund type dropdown
    "@shadcn/card",           // Procurement summary section
    "@shadcn/button",
    "@shadcn/badge",          // Status badges
    "@shadcn/toast",
    "@shadcn/alert",          // For warnings
    "@shadcn/separator"       // Section dividers
  ]
})
```

**Component Mapping**:
- Procurement summary → `Card` with read-only data
- Fund type dropdown → `Select`
- Status display → `Badge`
- Warnings → `Alert` variant="warning"

**File Updates Required**:
- `resources/js/Pages/PurchaseRequests/Create.tsx`
- `resources/js/Pages/PurchaseRequests/Edit.tsx`
- `resources/js/Pages/PurchaseRequests/Show.tsx`

---

### Story 2.6: Enhanced Reference Number Manual Input
**Status**: Ready
**Current Components**: Input fields with validation

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/input",
    "@shadcn/label",
    "@shadcn/form",
    "@shadcn/alert",          // For validation messages
    "@shadcn/popover",        // For format hints
    "@shadcn/button"
  ]
})
```

**Component Mapping**:
- Manual input field → `Input` with pattern validation
- Format hints → `Popover` with examples
- Validation errors → `Alert` variant="destructive"

---

### Story 2.7: Purchase Order (PO) Transaction Management
**Status**: Ready
**Current Components**: Similar to PR management

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/form",
    "@shadcn/select",         // Supplier dropdown
    "@shadcn/date-picker",    // PO date
    "@shadcn/input",          // PO number
    "@shadcn/card",
    "@shadcn/badge",
    "@shadcn/button",
    "@shadcn/toast"
  ]
})
```

**File Updates Required**:
- `resources/js/Pages/PurchaseOrders/Create.tsx`
- `resources/js/Pages/PurchaseOrders/Edit.tsx`
- `resources/js/Pages/PurchaseOrders/Show.tsx`

---

### Story 2.8: Voucher (VCH) Transaction Management
**Status**: Ready
**Current Components**: Forms and tables

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/form",
    "@shadcn/input",          // Payee, voucher number
    "@shadcn/date-picker",    // Voucher date
    "@shadcn/card",
    "@shadcn/badge",
    "@shadcn/button",
    "@shadcn/toast",
    "@shadcn/separator"
  ]
})
```

**File Updates Required**:
- `resources/js/Pages/Vouchers/Create.tsx`
- `resources/js/Pages/Vouchers/Edit.tsx`
- `resources/js/Pages/Vouchers/Show.tsx`

---

### Story 2.9: Procurement Detail View with Linked Transactions
**Status**: Ready
**Current Components**: Detail cards and timelines

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/card",           // Transaction cards
    "@shadcn/tabs",           // PR/PO/VCH tabs
    "@shadcn/badge",          // Status indicators
    "@shadcn/separator",
    "@shadcn/button",
    "@shadcn/accordion"       // Expandable sections
  ]
})

// Get timeline component example
await mcp__shadcn__get_item_examples_from_registries({
  registries: ["@shadcn"],
  query: "timeline"
})
```

**Component Mapping**:
- Transaction sections → `Tabs` or `Accordion`
- Transaction cards → `Card` with status `Badge`
- Timeline view → Custom component with shadcn styling

---

### Story 2.10: Transaction List & Search Functionality
**Status**: Ready
**Current Components**: Search filters and tables

**Components to Fetch via MCP**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/data-table",
    "@shadcn/input",          // Search field
    "@shadcn/select",         // Filter dropdowns
    "@shadcn/date-range-picker", // Date filters
    "@shadcn/button",
    "@shadcn/badge",
    "@shadcn/command"         // Advanced search
  ]
})

// Get advanced search example
await mcp__shadcn__get_item_examples_from_registries({
  registries: ["@shadcn"],
  query: "command-demo"
})
```

**Component Mapping**:
- Search interface → `Command` for advanced search
- Filters → Combination of `Select`, `DateRangePicker`
- Results table → `DataTable` with all features

---

## Global Component Updates

### Authentication Pages
**Files**: Login.tsx, Register.tsx, ForgotPassword.tsx, ResetPassword.tsx

**Components to Fetch**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/card",
    "@shadcn/form",
    "@shadcn/input",
    "@shadcn/button",
    "@shadcn/label",
    "@shadcn/checkbox",       // Remember me
    "@shadcn/alert"           // Error messages
  ]
})
```

### Dashboard
**File**: Dashboard.tsx

**Components to Fetch**:
```typescript
await mcp__shadcn__get_add_command_for_items({
  items: [
    "@shadcn/card",           // Stats cards
    "@shadcn/chart",          // If charts needed
    "@shadcn/table",          // Recent activity
    "@shadcn/badge",
    "@shadcn/button",
    "@shadcn/skeleton"        // Loading states
  ]
})
```

---

## Implementation Strategy

### Phase 1: Core Component Installation
1. Use MCP to fetch all base components
2. Set up lib/utils.ts with cn() helper
3. Configure CSS variables in app.css
4. Test each component in isolation

### Phase 2: Layout Migration
1. Update AuthenticatedLayout.tsx
2. Update GuestLayout.tsx
3. Ensure Toaster is globally available
4. Test navigation at all breakpoints

### Phase 3: Story-by-Story Updates
For each story with UI components:
1. Fetch required shadcn components via MCP
2. Update imports in affected files
3. Replace component usage following mapping guide
4. Test with Playwright MCP:
   ```typescript
   await mcp__playwright__browser_navigate({ url: pagePath })
   const errors = await mcp__playwright__browser_console_messages()
   await mcp__playwright__browser_take_screenshot({ path: `migration/${story}.png` })
   ```

### Phase 4: DataTable Standardization
All tables must be migrated to shadcn DataTable with:
- Column sorting
- Search/filter inputs
- Pagination controls
- Row selection
- Actions dropdown
- Export functionality (placeholder)

### Phase 5: Form Standardization
All forms must use shadcn Form components with:
- Proper validation display
- Loading states
- Success/error toasts
- Consistent styling

---

## Testing Requirements

For each migrated component:
1. **Console Check**: No errors in browser console
2. **Visual Test**: Screenshot comparison with baseline
3. **Responsive Test**: Check at mobile/tablet/desktop
4. **Interaction Test**: All buttons/forms functional
5. **ARIA Test**: Proper accessibility attributes

```typescript
// Standard test pattern for each page
const testPage = async (path: string) => {
  // Navigate
  await mcp__playwright__browser_navigate({ url: path })

  // Check console
  const logs = await mcp__playwright__browser_console_messages()
  const errors = logs.filter(l => l.level === 'error')
  assert(errors.length === 0, `Console errors found: ${errors}`)

  // Screenshot
  await mcp__playwright__browser_take_screenshot({
    path: `tests/${path.replace('/', '-')}.png`
  })

  // Test responsive
  const viewports = [
    { width: 375, height: 812 },   // Mobile
    { width: 768, height: 1024 },  // Tablet
    { width: 1920, height: 1080 }  // Desktop
  ]

  for (const viewport of viewports) {
    await mcp__playwright__browser_resize(viewport)
    await mcp__playwright__browser_take_screenshot({
      path: `tests/${path.replace('/', '-')}-${viewport.width}.png`
    })
  }
}
```

---

## Success Metrics

- ✅ All UI components fetched via MCP tools
- ✅ Zero mixed imports (old + new components)
- ✅ All tables are DataTables with full features
- ✅ All forms use shadcn Form components
- ✅ Console error-free across all pages
- ✅ Responsive design verified at all breakpoints
- ✅ Consistent UI/UX across entire application
- ✅ TypeScript types properly defined
- ✅ All stories pass acceptance criteria

---

## Notes

- This migration maintains all existing functionality
- No backend changes required
- Focus is purely on UI component standardization
- Use MCP tools exclusively for component fetching
- Follow the migration checklist phases
- Test thoroughly at each step