# Lectura — AI Teaching Management PWA

## Project Overview
Multi-tenant SaaS platform built with Laravel for managing the full teaching cycle.
See `docs/SRS-Lectura.md`, `docs/Architecture-Lectura.md`, `docs/Workflow-Lectura.md`.

## Tech Stack
- **Backend**: Laravel 13 (PHP 8.4)
- **Frontend**: Blade + Tailwind CSS + Alpine.js + Livewire 3
- **Real-time**: Laravel Reverb
- **Cache/Queue**: Redis
- **Database**: MySQL 8.0+ (dev: SQLite for quick testing)
- **File Storage**: Google Drive API (metadata in DB)
- **AI**: Provider-agnostic (Claude / OpenAI / Gemini)
- **Auth**: Breeze + Sanctum + Socialite

## Conventions

### Code Style
- Follow PSR-12
- Use strict types in all PHP files
- Models use `BelongsToTenant` trait for tenant-scoped tables
- Controllers use Form Requests for validation
- All tenant-scoped queries auto-filtered by `tenant_id` global scope
- Policies for authorization on all resource controllers

### Database
- All tenant-scoped tables have `tenant_id` as first FK after `id`
- Use `snake_case` for table/column names
- JSON columns for flexible/nested data
- Soft deletes where records may need recovery
- Foreign keys with appropriate ON DELETE actions

### Naming
- Models: singular PascalCase (`Course`, `TeachingPlan`)
- Controllers: `{Model}Controller`
- Form Requests: `{Action}{Model}Request` (`StoreCourseRequest`)
- Jobs: verb-based (`GenerateTeachingPlan`, `ProcessAiMarking`)
- Events: past-tense (`QuizResponseReceived`)
- Policies: `{Model}Policy`

### Multi-Tenancy
- Single database, `tenant_id` column approach
- Tenant resolved via subdomain or path prefix middleware
- Users are global; `tenant_users` pivot assigns roles per tenant
- Never query tenant-scoped data without tenant context

### Translations
- All user-facing strings must have translation keys
- Languages: `en` (English), `ms` (Bahasa Melayu)
- Use `__('module.key')` pattern

### Testing
- Feature tests for all controller actions
- Tenant isolation tests for every tenant-scoped model
- Mock AI providers in tests
- Use SQLite in-memory for test speed

## Commands
- `php artisan serve` — run dev server
- `php artisan test` — run tests
- `php artisan queue:work` — process jobs
- `npm run dev` — Vite dev server
- `npm run build` — production build
