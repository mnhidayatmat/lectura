# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Lectura is a multi-tenant SaaS platform built with Laravel for managing the full teaching cycle: courses, teaching plans, active learning plans (with AI generation), assignments with AI marking, live quizzes, QR attendance, course materials, random student wheel, and course file management. Supports per-user Pro subscription — Pro enables AI-assisted features. Lecturers can connect personal Google Drive for file storage. See `docs/SRS-Lectura.md`, `docs/Architecture-Lectura.md`, `docs/Workflow-Lectura.md` for full specs.

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

### Authentication & Onboarding

- Laravel Breeze scaffolding with email/password login and registration
- **Google OAuth** via Laravel Socialite (`laravel/socialite`): `GoogleController` handles redirect + callback
  - Routes: `GET /auth/google` → Google consent, `GET /auth/google/callback` → create/link/login user
  - New users created with `google_id` + `avatar_url`; existing email users auto-linked to Google account
  - Requires `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` in `.env`
  - Config in `config/services.php` under `google` key
- Users table has `google_id` (nullable, unique) and `avatar_url` fields for OAuth
- Password field is nullable to support passwordless Google-only accounts
- **Onboarding** (`/onboarding`): new users with no tenant see role selection page
  - Choose existing institution from dropdown OR create a new one by typing name
  - Select role: Lecturer or Student (visual card selection)
  - Students can enter section invite code for auto-enrollment
  - First lecturer to create an institution gets admin role
  - `OnboardingController` handles both flows
- After login, users redirect to their first active tenant dashboard; if none, to onboarding

### Routing

- **No API routes** — server-rendered Blade app only (`routes/web.php`)
- Auth routes in `routes/auth.php` (Breeze + Google OAuth)
- Tenant routes use `{tenant:slug}` prefix with middleware group: `auth`, `tenant`, `tenant.access`, `locale`
- Route names prefixed with `tenant.` (e.g., `tenant.courses.index`, `tenant.assignments.show`)
- Admin routes under `/admin` prefix with inline super_admin check
- Google Drive callback at `/settings/drive/callback` (outside tenant prefix, Google redirects here directly)

### Subscription Model (Per-User Pro)

- Pro tier is **per-user**, not per-institution. `users.is_pro` boolean column
- `User::isPro()` / `User::isFree()` for checks
- Pro features gated by `TierGateService::assertProFeature(auth()->user(), $featureName)` (throws 402 if free)
- Pro enables: AI plan generation, AI group arrangement, AI-assisted marking, per-tenant API key management
- Admin can toggle any user's Pro status at `/admin/users`
- Per-tenant AI API keys stored encrypted in `tenants.settings->ai->api_keys->{provider}`

### Service Layer

- `App\Services\AI\AiServiceManager` — singleton, resolves provider (Claude/OpenAI/Gemini); checks tenant API key first (Pro), then global env, then MockProvider fallback. Call `resetProvider()` in queue jobs to clear cached provider
- `App\Services\AI\Contracts\AiProviderInterface` — implement this for new AI providers
- `App\Services\AI\Providers\MockProvider` — used for development/testing without API keys
- `App\Services\AI\ActiveLearningGeneratorService` — builds AI prompt from topic/CLOs/lecture notes, parses structured JSON response into activities
- `App\Services\AI\AiGroupingService` — AI-suggested group arrangements from attendance data
- `App\Services\ActiveLearning\ActiveLearningPlanService` — CRUD + publish + activity reorder
- `App\Services\ActiveLearning\ActivityService` — activity CRUD with auto-sequencing
- `App\Services\ActiveLearning\GroupingService` — group creation, member management, auto-arrange from attendance sessions
- `App\Services\ActiveLearning\TierGateService` — Pro/Free feature gating (checks `User::isPro()`)
- `App\Services\Attendance\QrCodeService` — HMAC-based rotating QR tokens
- `App\Services\CourseFile\FolderService` — course folder management with default templates
- `App\Services\Tenant\TenantResolver` — subdomain/path tenant resolution strategy
- `App\Services\GoogleDriveService` — Google Drive API v3 integration (OAuth, folder/file management, quota)

### Controllers

All tenant-scoped controllers live in `App\Http\Controllers\Tenant\`. Controllers return Blade views (not JSON). Sub-feature controllers are namespaced (e.g., `Tenant\ActiveLearning\ActiveLearningPlanController`).

Key controllers:
- `CourseMaterialController` — weekly material management (lecturer upload/link) + student read-only view
- `StudentMarkController` — student marks & feedback dashboard
- `RandomWheelController` — random present student wheel for classroom participation
- `CourseFileController` — folder-based course file management (compliance/archive)
- `SettingsController` — lecturer settings page with Google Drive connection/disconnect
- `AttendanceController` — QR attendance with real-time check-in list (polls every 5s)

### Frontend Stack

- Blade templates in `resources/views/`, organized by domain (`tenant/courses/`, `tenant/assignments/`, etc.)
- Tailwind CSS (with `darkMode: 'class'`) + Alpine.js for interactivity
- Dark mode: toggled via Alpine.js `darkMode()` function, persisted in `localStorage`, respects OS `prefers-color-scheme`. Custom "Dimmed" palette in `resources/css/app.css` using CSS `@layer base` overrides — warm blue-grey tones (#1c2333 canvas, #242d3d surface, #354158 borders), never pure black
- Livewire 4 for reactive components (quizzes, attendance)
- Laravel Echo + Pusher.js for WebSocket (via Laravel Reverb)

### App Configuration

Custom config in `config/lectura.php` covers: tenant resolution, AI providers (Claude/OpenAI/Gemini with model selection), attendance (QR rotation, late threshold), file uploads (size limits, allowed types), quiz settings, and default folder templates.

### Google Drive Storage

- Lecturers can connect their personal Google Drive at `/{tenant}/settings`
- OAuth flow via `google/apiclient` package with `drive.file` scope
- Tokens stored per-user: `drive_access_token`, `drive_refresh_token`, `drive_token_expires_at`, `drive_root_folder_id`
- `User::isDriveConnected()` checks if Drive is linked
- `GoogleDriveService` handles: auth URL generation, callback token exchange, automatic token refresh, folder creation, file upload, storage quota retrieval, disconnect/revoke
- Auto-creates "Lectura" root folder in lecturer's Drive on first connect
- Requires `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REDIRECT_URI` in `.env`
- Config in `config/services.php` under `google_drive` key
- Google Drive API must be enabled in Google Cloud Console

### Active Learning Plans

- Plans link to a `Course` and optionally to a `CourseTopic` (week)
- Each plan contains ordered `ActiveLearningActivity` records (types: individual, pair, group, discussion, reflection, whole_class)
- Group activities support `ActiveLearningGroup` with members from attendance sessions
- Published plans remain fully editable: lecturers can add, edit, delete activities and manage groups after publishing. Only the publish button and delete-plan action are hidden for published plans
- AI generation (Pro): dispatches `GenerateActiveLearningPlan` job, which calls `ActiveLearningGeneratorService` to produce activities from topic + CLOs + optional lecture notes. Edit view polls `generationStatus` endpoint via Alpine.js
- AI grouping (Pro): dispatches `ArrangeGroupsWithAi` job using `AiGroupingService`
- Admin AI settings (Pro): tenant admins manage their own API keys at `/{tenant}/admin/ai-settings`

### Course Materials

- Weekly-organized material system (separate from folder-based Course Files)
- Lecturers upload files or add external links (video URLs, etc.) organized by week number
- Students see read-only weekly accordion view of their enrolled courses
- `CourseFile` model supports both `material_type='file'` and `material_type='link'`
- Routes: `/materials` (lecturer), `/my-materials` (student)

### Assignments & Marking

- Assignments support `marking_mode`: `manual` or `ai_assisted`
- Answer scheme: text field + optional PDF upload (`answer_scheme_path`, `answer_scheme_filename`) with 3-mode toggle (Text / Upload PDF / Both)
- AI marking generates `MarkingSuggestion` records with confidence scores
- `StudentMark` stores finalized grades; `Feedback` stores released feedback (strengths, improvements, missing points, misconceptions, revision advice)
- Student marks dashboard at `/{tenant}/marks` with filter tabs (All/Graded/Pending), expandable inline feedback

### Attendance

- QR-based attendance with rotating tokens (configurable interval, default 30s)
- **Real-time check-in list**: lecturer's QR page polls every 5 seconds for new check-ins
- New student flash banner: "[Name] just checked in!" with green highlight, auto-dismisses after 3s
- `refreshToken` API returns full records array (name, status, time) sorted newest first
- Student scan page with camera QR detection via `html5-qrcode`
- Auto-marks absent students when session ends
- Manual status override: present, late, absent, excused

### Random Present Student Wheel

- Lecturer tool for randomly selecting present students during class
- Loads only students with `status='present'` (optionally `'late'`) from `AttendanceRecord`
- Canvas-based wheel with crypto-random selection (`crypto.getRandomValues`) and smooth cubic ease-out animation (4.5-6s)
- Auto-remove winner, spin history with timestamps, session persistence via `sessionStorage`
- Fullscreen mode for projector/classroom use
- Route: `/{tenant}/random-wheel`

### Admin Panel

- Super admin dashboard at `/admin` with institution, user, AI usage, and activity management
- **Impersonate ("View As")**: admin can switch to any user's perspective via dropdown in admin topbar. Session stores `impersonator_id`; amber banner shown on tenant pages with "Stop Viewing" button
- **User management**: toggle Pro/Free status, view role (Lecturer/Student/Admin/Coordinator) at `/admin/users`
- **Institution management**: create institutions with name, slug, timezone, locale; view lecturer/student counts at `/admin/tenants`
- **AI Usage monitoring**: real-time dashboard at `/admin/ai-usage` with period filter, breakdowns by module, provider, and institution
- **AI Provider settings**: manage provider configs at `/admin/ai-settings`
- **Activity log**: system-wide audit trail with filters by log name and event at `/admin/activity`

## Conventions

### Code Style
- PSR-12 with `declare(strict_types=1)` in all PHP files
- Models use `BelongsToTenant` trait for tenant-scoped tables
- Controllers use Form Requests for validation (`{Action}{Model}Request`)
- Policies for authorization on all resource controllers
- Activity logging via `spatie/laravel-activitylog` on key models

### Naming
- Models: singular PascalCase (`Course`, `TeachingPlan`, `ActiveLearningPlan`)
- Controllers: `{Model}Controller` (tenant controllers in `Tenant\` namespace, sub-features in `Tenant\{Feature}\`)
- Jobs: verb-based (`GenerateTeachingPlan`, `GenerateActiveLearningPlan`, `ArrangeGroupsWithAi`)
- Events: past-tense (`QuizResponseReceived`)
- Route names: `tenant.{resource}.{action}` (e.g., `tenant.active-learning.index`, `tenant.active-learning.activities.store`)
- Translation files: `snake_case` module name (e.g., `active_learning.php`)

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

### Environment Variables

Key `.env` variables for external services:
```
# Google OAuth (Login)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback

# Google Drive (Storage)
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REDIRECT_URI=${APP_URL}/settings/drive/callback

# AI Providers
AI_DEFAULT_PROVIDER=claude
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=
```
