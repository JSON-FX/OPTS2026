# Unified Project Structure

This document describes the canonical directory layout for OPTS. Follow these conventions when adding new backend or frontend artifacts.

```
opts2026/
├── app/
│   ├── Console/                  # Artisan commands
│   ├── Exceptions/               # Domain-specific exceptions
│   ├── Http/
│   │   ├── Controllers/          # Inertia + API controllers
│   │   ├── Middleware/           # HTTP middleware
│   │   └── Requests/             # Form Request validators
│   ├── Models/                   # Eloquent models
│   ├── Policies/                 # Authorization policies
│   ├── Providers/                # Service providers
│   └── Services/                 # Domain/application services
├── bootstrap/                    # Framework bootstrap
├── config/                       # Laravel configuration
├── database/
│   ├── factories/                # Model factories
│   ├── migrations/               # Schema migrations
│   └── seeders/                  # Seed data
├── public/                       # Web root (compiled assets)
├── resources/
│   ├── css/                      # Tailwind entry point
│   ├── js/
│   │   ├── Components/           # Shared React components
│   │   │   └── ui/               # Shadcn component wrappers
│   │   ├── Hooks/                # Reusable hooks (optional)
│   │   ├── Layouts/              # Global layouts
│   │   ├── Pages/                # Inertia pages by domain
│   │   ├── Types/                # Shared TypeScript interfaces
│   │   └── app.tsx               # Inertia bootstrapping
│   └── views/                    # Blade templates (root Inertia view)
├── routes/
│   ├── web.php                   # Inertia-bound routes
│   └── api.php                   # REST endpoints (limited use)
├── storage/                      # Logs, cache, compiled views
├── tests/
│   ├── Feature/                  # Laravel feature tests
│   │   ├── Auth/                 # Auth-specific features
│   │   ├── Migrations/           # Schema validation tests
│   │   └── Services/             # Service-level feature tests
│   ├── Support/                  # Testing utilities/traits
│   └── Unit/                     # Pure unit tests
├── docs/
│   ├── architecture/             # Architecture references (this doc)
│   └── stories/                  # Story requirements
├── .bmad-core/                   # BMAD agent configuration
├── package.json                  # Frontend dependencies + scripts
├── composer.json                 # PHP dependencies + scripts
├── vite.config.ts                # Vite build configuration
├── tailwind.config.js            # Tailwind configuration
└── phpunit.xml                   # PHPUnit configuration
```

## Naming Conventions

- **Domains in Pages**: create subfolders for logical areas, e.g., `Pages/Admin/Users`, `Pages/Procurements`.
- **Shared UI**: place reusable UI primitives in `Components/ui`. Composite widgets live directly under `Components`.
- **Services**: keep application services in `app/Services`. Group by domain if there are multiple (e.g., `Procurements/ProcurementService.php` in a nested folder).
- **Tests**: mirror application namespaces; e.g., `tests/Feature/Procurements/ProcurementControllerTest.php`.

## Adding New Modules

1. **Backend**: add the necessary controller, request, service, model updates, and migrations. Register routes in `routes/web.php` or `routes/api.php`.
2. **Frontend**: create a new directory under `Pages/{Domain}` housing Inertia pages and supporting components.
3. **Types**: update `resources/js/types` when exposing new data to the frontend.
4. **Documentation**: update relevant docs under `docs/architecture` and story files.

This unified structure keeps the monolith organized while allowing focused domain boundaries.

