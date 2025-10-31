# Source Tree Reference

This tree provides a quick overview of key directories and files in the OPTS repository. Use it to locate existing modules or decide where new code should live.

```
opts2026/
├── app/
│   ├── Exceptions/
│   │   └── ReferenceNumberException.php
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── Services/
│       └── ReferenceNumberService.php
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── ProcurementSeeder.php
│   │   └── TransactionSeeder.php
├── docs/
│   ├── architecture/
│   └── stories/
├── public/
├── resources/
│   ├── css/
│   └── js/
│       ├── Components/
│       ├── Layouts/
│       ├── Pages/
│       └── Types/
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   ├── Migrations/
│   │   ├── Services/
│   │   └── ...
│   ├── Support/
│   └── Unit/
├── .bmad-core/
├── composer.json
├── package.json
├── phpunit.xml
├── tailwind.config.js
└── vite.config.ts
```

> **Tip:** When adding new functionality, mirror the existing structure—for example, placing service tests under `tests/Feature/Services` or UI components under `resources/js/Components`.

