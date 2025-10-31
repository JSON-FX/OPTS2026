# Component Catalog & Guidelines

This reference catalogs reusable UI components and establishes expectations for creating new ones. All components live under `resources/js/Components`.

## Component Categories

1. **UI Primitives (`Components/ui/`)**
   - Shadcn-generated wrappers (Button, Input, Select, Dialog, Toast, Table, Tabs).
   - Extend these with project-specific variants (e.g., `Button` with role-based coloring).
   - Avoid modifying generated primitives directly; create wrappers if additional behavior is required.

2. **Data Display Components**
   - `DataTable.tsx`: configurable table supporting sorting, pagination, and optional bulk actions.
   - `StatusBadge.tsx`: color-coded badges for procurement/transaction statuses.
   - `EmptyState.tsx`: standardized empty results messaging with optional CTA.

3. **Form Controls**
   - `FormSection.tsx`: layout wrapper for grouping form inputs with headings/descriptions.
   - `CurrencyInput.tsx`: specialized numeric input for currency values.
   - `DatePicker.tsx`: wrapper around Radix/React Date Picker with constraints (e.g., no future dates).

4. **Feedback Components**
   - `ToastProvider` / `ToastViewport`: global container included in `AuthenticatedLayout`.
   - `ConfirmDialog.tsx`: generic confirmation modal used for destructive actions (e.g., soft delete).

5. **Navigation / Layout**
   - `SideNav.tsx`: role-aware navigation links.
   - `TopBar.tsx`: includes breadcrumbs, page title, action buttons.

## Creation Guidelines

- Place domain-agnostic components in `Components/`.
- Domain-specific composites should live alongside their pages (e.g., `Pages/Procurements/components/`).
- Export components via index barrels for cleaner imports.
- Document props with TypeScript interfaces; include JSDoc comments for complex behavior.
- Keep components pure; external data fetching should happen in pages/hooks, not components.

## Styling Standards

- Use Tailwind utility classes; create helper classnames constants when re-used frequently.
- Respect dark mode tokens (if enabled) by referencing `bg-background`, `text-foreground`, etc.

## Accessibility Checklist

- Components must be keyboard accessible.
- Ensure ARIA attributes are applied when required (e.g., dialogs, tooltips).
- Provide visible focus states for interactive elements.

## Testing Expectations

- For reusable logic-heavy components, add Vitest tests under `Components/__tests__/`.
- Snapshot testing can be used sparingly; prefer interaction-focused tests with RTL.

Keep this catalog updated as new shared components are introduced.

