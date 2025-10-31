# Frontend Architecture

The OPTS frontend is built with React 19, TypeScript, Inertia.js, Tailwind CSS, and the Shadcn/UI component library. This document explains the structure and patterns to follow when building UI features.

## High-Level Overview

- **Inertia.js** bridges Laravel routes to React components. Each route returns an Inertia response that mounts a React page.
- **Layouts** wrap pages to provide consistent navigation, headers, and toasts.
- **Components** live in `resources/js/Components` and encapsulate reusable UI or logic.
- **Shadcn/UI** provides accessible primitives customized via Tailwind.
- **State management** uses React hooks and local component state; prefer Inertia form helpers (`useForm`) for form submission state.

## Directory Structure

```
resources/js/
├── Components/
│   ├── ui/                  # Shadcn wrappers (Button, Input, Table, etc.)
│   ├── DataTable.tsx        # Example data table composite component
│   └── ...                  # Domain-specific shared components
├── Layouts/
│   ├── AuthenticatedLayout.tsx
│   └── GuestLayout.tsx
├── Pages/
│   ├── Dashboard.tsx
│   ├── Procurements/
│   │   ├── Index.tsx
│   │   ├── Create.tsx
│   │   ├── Edit.tsx
│   │   └── Show.tsx
│   └── ...
├── Types/
│   ├── models.ts            # Shared domain interfaces
│   └── index.d.ts           # Inertia type augmentation
└── app.tsx                  # Inertia bootstrapping
```

## Page Guidelines

- Pages should be organized by domain (e.g., `Pages/Procurements`).
- Each page receives typed props (`PageProps`) ensuring strong typing for Inertia payloads.
- Use layout slots by wrapping component output in `AuthenticatedLayout`.
- Side effects (e.g., flash messages) handled via hooks triggered from Inertia shared props.

## Component Patterns

- **Presentation components**: stateless, accept props for data and callbacks.
- **Container components**: minimal; often the page itself. Use hooks to fetch or mutate via Inertia.
- **Form components**: leverage `useForm` for validation errors, disabling submit button while processing.
- **Tables**: use Shadcn table + headless filter controls; for large tables consider virtualization later.

## Styling & Theming

- Tailwind CSS with design tokens set in `tailwind.config.js`.
- Follow Shadcn default spacing/typography to maintain consistency.
- Add custom classes sparingly; prefer composing existing utilities.

## Accessibility

- Use Shadcn components (Radix under the hood) for built-in accessibility.
- Provide semantic HTML structure and ARIA labels when needed.
- Ensure form inputs have associated labels from `InputLabel`.

## Client-Side Validation & Feedback

- Rely on server validation via Form Requests for authoritative checks.
- You may add lightweight client validation for immediate feedback, but ensure server errors surface correctly (display `errors` from Inertia props near fields).
- Show success/error toasts using Shadcn toast primitives triggered by flash messages.

## Navigation & Routing

- Use Inertia `Link` components for navigation.
- Update top-level navigation in `AuthenticatedLayout` when introducing new sections.
- Respect RBAC: hide or disable navigation links based on `auth.user.roles`.

## Performance

- Split code by page automatically via Vite.
- Avoid unnecessary memoization unless profiling identifies issues.
- Debounce expensive search/filter inputs.

## Testing

- Write component-level tests with Vitest/RTL for logic-heavy or reusable components.
- Smoke-test pages where behavior is complex (multi-step forms, modal flows).

Following these patterns ensures a maintainable and consistent frontend experience.

