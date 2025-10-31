# REST API Specification

While OPTS primarily uses Inertia for server-driven views, a limited REST surface is available (and may expand in future epics). This document records exposed endpoints and design conventions.

## Design Guidelines

- Base URL: `/api`
- Authentication: Laravel Sanctum (token-based) for programmatic access.
- Responses: JSON, snake_case keys for backward compatibility.
- Errors: Standard JSON with `message`, optional `errors` array for validation.
- Versioning: Current API is unversioned (`/api/...`). Introduce `/api/v1` once stability requires.

## Current Endpoints

| Method | Endpoint                  | Auth         | Description                          |
|--------|---------------------------|--------------|--------------------------------------|
| GET    | `/api/health`             | Public       | Lightweight health probe (200 OK).   |
| GET    | `/api/procurements`       | Sanctum      | Paginated list of procurements with filters. |
| POST   | `/api/procurements`       | Sanctum      | Create a procurement (same rules as web). |
| GET    | `/api/procurements/{id}`  | Sanctum      | Procurement detail with linked transactions. |
| PATCH  | `/api/procurements/{id}`  | Sanctum      | Update procurement (subject to edit rules). |
| DELETE | `/api/procurements/{id}`  | Sanctum      | Soft delete or archive.              |

> **Note:** As of Epic 2, these endpoints are optional; the full implementation may be delivered in later milestones. Stories will specify when to extend the API surface.

## Query Parameters

- `search`, `status`, `end_user_id`, `particular_id`, `created_by`: Filter parameters for listing endpoints.
- `per_page`: Number of results per page (default 50, max 100).

## Pagination Format

Conforms to Laravel paginator JSON structure:

```json
{
  "data": [ ... ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "...",
  },
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 125
  }
}
```

## Validation Errors

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Validation message..."]
  }
}
```

## Rate Limiting

- Use Laravel’s default throttle middleware (`60 requests / minute`) for authenticated endpoints.
- Adjust per endpoint as needed for heavy operations.

Keep this spec updated as new API endpoints are added or existing ones change.

