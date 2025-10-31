# Testing Strategy

This document defines the layered testing approach for OPTS. Every story should include tests appropriate to the impacted layers.

## Goals

- Prevent regressions across backend and frontend.
- Ensure business rules and RBAC enforcement are verifiable.
- Provide fast feedback for developers and CI pipelines.

## Test Pyramid

1. **Unit Tests (Fast, Many)**
   - Location: `tests/Unit`
   - Scope: Pure functions, services with mocked dependencies, validation logic.
   - Tools: PHPUnit.
   - Examples: Service date calculations, reference number overflow handling, custom helpers.

2. **Feature Tests (Balanced)**
   - Location: `tests/Feature`
   - Scope: HTTP endpoints, Inertia responses, policy enforcement, middleware chains, database interactions.
   - Tools: Laravel feature testing utilities.
   - Guidelines:
     - Use database transactions via `RefreshDatabase`.
     - Seed required reference data with dedicated seeders/factories.
     - Assert view props / Inertia payloads.

3. **Frontend Tests**
   - Location: `resources/js` (future `__tests__` directories as needed).
   - Scope: Component behavior, hooks, form validation, complex UI state.
   - Tools: Vitest + React Testing Library.
   - Run with `npm run test`.

4. **End-to-End / Browser Tests (Targeted)**
   - Tooling: Playwright (planned).
   - Scope: Critical workflows (login, procurement lifecycle, approvals).
   - Execute in CI nightly or before release.

## Cross-Cutting Expectations

- **Factories & Seeders**: Use Laravel factories to create realistic data for tests. Seeders should include deterministic values for assertions.
- **Transactions**: Wrap multi-step feature tests in transactions (`RefreshDatabase`) to isolate state.
- **Assertions**:
  - Validate database state with `assertDatabaseHas`.
  - Verify Inertia props via `assertInertia`.
  - Ensure authorization failures return appropriate HTTP codes.

## Performance & Concurrency

- Use Laravel’s concurrency helpers (`Bus::batch`, `ParallelTesting`) to test race conditions where required (e.g., reference number generation).
- Add stress tests for queue workers or background jobs if they manipulate critical data.

## CI Integration

- Backend: `php artisan test`
- Frontend: `npm run lint && npm run test`
- Static analysis (optional future addition): Laravel Pint, PHPStan, ESLint.

## Reporting

- Record test results in story Dev Agent Records.
- Include coverage of acceptance criteria when summarizing QA evidence.

Adhering to this strategy keeps confidence high as the procurement lifecycle features evolve.

