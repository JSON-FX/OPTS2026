# Introduction

## Purpose and Scope

This document defines the complete technical architecture for **OPTS (Online Procurement Tracking System)**, a web-based procurement tracking platform for Local Government Units (LGUs). The architecture encompasses backend services, frontend components, database design, deployment infrastructure, and development workflows necessary to implement all requirements defined in the Product Requirements Document v1.1.

The architecture addresses:
- **Core Domain:** Procurement lifecycle management through three sequential transactions (PR → PO → VCH)
- **Workflow Engine:** Configurable office-to-office routing with SLA tracking and misroute detection
- **Audit & Compliance:** Immutable audit trails and 7-year data retention (NFR14)
- **Performance:** P95 page load < 2.5s with 200 concurrent users and 100k transactions (NFR5)
- **Security:** RBAC with three roles (Viewer, Endorser, Administrator), HTTPS enforcement, secure password storage

## Starter Template Used

**Laravel 12.x Breeze React Starter Kit**

This architecture builds upon the official Laravel Breeze React starter template, which provides:
- Laravel 12.x backend framework (PHP 8.2+)
- React 19 with TypeScript via Inertia.js
- Tailwind CSS + Shadcn/UI component library (included by default)
- Vite build tooling
- Authentication scaffolding (login, registration, password reset)
- Session-based authentication with CSRF protection

**Installation Command:**
```bash
composer create-project laravel/laravel opts2026
cd opts2026
composer require laravel/breeze --dev
php artisan breeze:install react
npm install
```

**Rationale:** This starter eliminates 40+ hours of initial setup, provides industry-standard authentication patterns, and establishes TypeScript + Inertia.js conventions from day one. All customizations in this architecture extend (not replace) Breeze foundations.

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2025-10-31 | 1.0 | Initial Architecture Document from PRD v1.1 | Architect Agent |

---
