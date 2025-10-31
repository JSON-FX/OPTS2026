# Coding Standards

This guide defines the coding conventions for OPTS across backend (Laravel PHP) and frontend (React + TypeScript). Follow these standards for every change unless a story explicitly overrides them.

## General Principles

- **Clarity over cleverness** – Prefer readable, intention-revealing code with descriptive names.
- **Consistency first** – Align with existing patterns in the codebase; when in doubt, mirror the closest working example.
- **Small, composable units** – Break complex logic into methods/components with single responsibilities.
- **Document decisions** – Use concise, high-value comments to explain intent or non-obvious reasoning (avoid restating code).
- **Fail fast** – Validate inputs early and return informative validation errors to the UI.

## PHP / Laravel Standards

- PHP 8.2+ strict typing; declare `strict_types=1` when creating new files.
- Use PSR-12 formatting (Pint default). Run `./vendor/bin/pint` before committing.
- Controller methods should be thin:
  - Validate via Form Request classes.
  - Delegate business logic to Service or Action classes.
  - Return Inertia responses or redirects only.
- Eloquent models:
  - Guard attributes using `$fillable` (never rely on `$guarded = []`).
  - Cast attributes and enums explicitly.
  - Prefer query scopes for reusable filters.
  - Avoid N+1 queries with `with()` or `load()` in controllers.
- Use Laravel’s dependency container for service resolution; type-hint interfaces where possible.
- Database access must be wrapped in transactions for multi-step mutations.

### Naming

- Controllers: `ResourceNameController` (e.g., `ProcurementController`).
- Form requests: `StoreResourceRequest`, `UpdateResourceRequest`.
- Services: `ResourceActionService` or domain-specific (e.g., `ReferenceNumberService`).
- Jobs/Listeners: Verb-based (`GenerateProcurementReport`).
- Use StudlyCase for classes, camelCase for methods/properties, snake_case for database columns.

### Error Handling

- Throw domain-specific exceptions (extend `RuntimeException`) for recoverable errors; catch them at controller or middleware boundaries.
- Utilize Laravel’s `abort*` helpers for HTTP error responses in controllers.
- Log unexpected exceptions with context; avoid swallowing errors silently.

## JavaScript / TypeScript Standards

- TypeScript 5.x strict mode – all files must compile without `any` except for deliberate `unknown` -> `assert`.
- Enforce ESLint (`npm run lint`) and Prettier (`npm run format`).
- Functional React components only; use hooks for state/effects.
- Prefer composition over inheritance; extract reusable UI into Shadcn-based primitives.
- Keep component props typed; export shared types from `resources/js/types`.
- Use Inertia `useForm` for form state + validation; map server errors to inputs.
- Follow Tailwind utility classes; avoid custom CSS unless necessary.

### File & Component Conventions

- Pages: `resources/js/Pages/{Domain}/{PageName}.tsx`.
- Shared components: `resources/js/Components` or `resources/js/Components/ui` (Shadcn wrappers).
- Hook files: `useSomething.ts`.
- Prefer explicit re-exports (`index.ts`) for module barrels.

## Git & Documentation

- Atomic commits scoped around a single feature or fix.
- Update story Dev Agent Records with modification summaries.
- Document new environment variables in `.env.example` and supporting readme sections.

## Tools & Automation

- Backend formatting: `./vendor/bin/pint`.
- Backend tests: `php artisan test`.
- Frontend formatting/linting: `npm run format`, `npm run lint`.
- Frontend unit tests: `npm run test` (Vitest).
- Run `php artisan migrate --pretend` on schema changes before actual migration.

Adhering to these standards ensures predictable, maintainable code across the OPTS project.

