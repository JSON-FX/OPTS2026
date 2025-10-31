# Backend Architecture

The backend is a Laravel 12.x monolith that exposes both Inertia-driven web interfaces and limited REST endpoints. This document outlines how to structure backend code and where responsibilities lie.

## Layers & Responsibilities

1. **Controllers (app/Http/Controllers)**
   - Entry point for HTTP requests (web + API).
   - Minimal logic: validate input via Form Requests, invoke services, return responses.
   - For Inertia routes, pass serialized data to frontend via `Inertia::render`.

2. **Requests (app/Http/Requests)**
   - Extend `FormRequest`.
   - Define validation rules, authorize access (role checks, ownership).
   - Use helper methods to convert validated data to DTO-like arrays.

3. **Services (app/Services)**
   - Business logic orchestration (e.g., reference number generation, procurement workflows).
   - Coordinate repositories/models, handle transactions, emit events.
   - Pure PHP classes injected through the container (registered in `AppServiceProvider`).

4. **Models (app/Models)**
   - Eloquent models map database tables.
   - Define relationships, attribute casting, accessors/mutators, query scopes.
   - Use events (`creating`, `updating`) for lightweight automations (avoid heavy business logic here).

5. **Policies (app/Policies)**
   - Authorization decisions beyond simple role checks.
   - Use with controllers via `authorize()` or `this->authorizeResource`.

6. **Events / Jobs**
   - Emit events for asynchronous or side-effect heavy operations (notifications, reporting).
   - Queue jobs via Laravel Queue when processing out-of-band tasks.

## Request Lifecycle (Web)

1. Route defined in `routes/web.php`.
2. Middleware enforces auth, role permissions, etc.
3. Controller validated by Form Request → Service executes business logic.
4. Data returned through Inertia page with relevant props.

## Request Lifecycle (API)

1. Optional REST endpoints in `routes/api.php`, typically protected via Sanctum.
2. Return JSON resources (`JsonResource`) to ensure consistent serialization.

## Transactions & Consistency

- Wrap multi-step data mutations in `DB::transaction`.
- Utilize `lockForUpdate` when dealing with concurrent writes (e.g., counters).
- Services should throw domain-specific exceptions for recoverable errors; controllers translate to HTTP responses.

## Configuration & Environment

- Store configuration in `config/` and make use of environment variables.
- Provide sensible defaults; document new env vars in README and `.env.example`.
- Use service providers to register bindings, observers, macros.

## Logging & Monitoring

- Laravel’s default logging (`storage/logs/laravel.log`) is sufficient for dev.
- Introduce structured logging or external monitoring (e.g., Telescope, Pulse) as per NFRs.

## Testing

- Unit tests focus on services/helpers.
- Feature tests cover controllers, middleware, and database interactions.
- Always seed using factories or designated seeders for deterministic data.

Following this architecture keeps backend logic modular, testable, and aligned with Laravel best practices.

