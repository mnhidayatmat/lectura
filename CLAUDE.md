# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Lectura is a multi-tenant SaaS platform built with Laravel for managing the full teaching cycle: courses, teaching plans, assignments with AI marking, live quizzes, QR attendance, and course file management. See `docs/SRS-Lectura.md`, `docs/Architecture-Lectura.md`, `docs/Workflow-Lectura.md` for full specs.

## Commands

```bash
# Development (runs server, queue, logs, vite concurrently)
composer dev

# Run all tests (clears config cache first)
composer test

# Run a single test file
php artisan test --filter=CourseControllerTest

# Run a single test method
php artisan test --filter=CourseControllerTest::test_store_creates_course

# Code formatting
./vendor/bin/pint

# First-time setup
composer setup

# Production frontend build
npm run build
```

## Architecture

### Multi-Tenancy (Single DB, tenant_id column)

- `BelongsToTenant` trait (`app/Traits/`) adds a global scope filtering by `tenant_id` and auto-sets it on `creating`
- Tenant resolved in middleware from URL: path prefix (`/{tenant:slug}/...`) or subdomain, configured via `TENANT_RESOLVER` env
- `ResolveTenant` middleware binds tenant to container as `current_tenant`; `EnsureTenantAccess` checks user membership (super_admin bypasses)
- Users are global; roles assigned per-tenant via `tenant_users` pivot. Check role with `$user->roleInTenant($tenantId)`
- All tenant-scoped tables have `tenant_id` as first FK after `id`

### Routing

- **No API routes** — server-rendered Blade app only (`routes/web.php`)
- Tenant routes use `{tenant:slug}` prefix with middleware group: `auth`, `tenant`, `tenant.access`, `locale`
- Route names prefixed with `tenant.` (e.g., `tenant.courses.index`, `tenant.assignments.show`)
- Admin routes under `/admin` prefix with inline super_admin check

### Service Layer

- `App\Services\AI\AiServiceManager` — registered as singleton, resolves provider (Claude/OpenAI/Gemini) from `config/lectura.php`
- `App\Services\AI\Contracts\AiProviderInterface` — implement this for new AI providers
- `App\Services\AI\Providers\MockProvider` — used for development/testing without API keys
- `App\Services\Attendance\QrCodeService` — HMAC-based rotating QR tokens
- `App\Services\CourseFile\FolderService` — course folder management with default templates
- `App\Services\Tenant\TenantResolver` — subdomain/path tenant resolution strategy

### Controllers

All tenant-scoped controllers live in `App\Http\Controllers\Tenant\`. Controllers return Blade views (not JSON).

### Frontend Stack

- Blade templates in `resources/views/`, organized by domain (`tenant/courses/`, `tenant/assignments/`, etc.)
- Tailwind CSS + Alpine.js for interactivity
- Livewire 4 for reactive components (quizzes, attendance)
- Laravel Echo + Pusher.js for WebSocket (via Laravel Reverb)

### App Configuration

Custom config in `config/lectura.php` covers: tenant resolution, AI providers, attendance (QR rotation, late threshold), file uploads (size limits, allowed types), quiz settings, and default folder templates.

## Conventions

### Code Style
- PSR-12 with `declare(strict_types=1)` in all PHP files
- Models use `BelongsToTenant` trait for tenant-scoped tables
- Controllers use Form Requests for validation (`{Action}{Model}Request`)
- Policies for authorization on all resource controllers

### Naming
- Models: singular PascalCase (`Course`, `TeachingPlan`)
- Controllers: `{Model}Controller` (tenant controllers in `Tenant\` namespace)
- Jobs: verb-based (`GenerateTeachingPlan`, `ProcessAiMarking`)
- Events: past-tense (`QuizResponseReceived`)
- Route names: `tenant.{resource}.{action}`

### Database
- `snake_case` for table/column names
- JSON columns for flexible/nested data
- Soft deletes where records may need recovery
- Foreign keys with appropriate ON DELETE actions

### Translations
- All user-facing strings use `__('module.key')` pattern
- Languages: `en` (English), `ms` (Bahasa Melayu)

### Testing
- PHPUnit with SQLite in-memory (`:memory:`)
- Feature tests for controller actions, tenant isolation tests for scoped models
- Mock AI providers in tests — never call real AI APIs
- Queue set to `sync`, cache to `array` in test env
