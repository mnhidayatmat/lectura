# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Lectura is a multi-tenant SaaS platform built with Laravel for managing the full teaching cycle: courses, active learning plans (with AI generation), assignments with AI marking, live quizzes, QR attendance, course materials, random student wheel, and course file management. Supports per-user Pro subscription — Pro enables AI-assisted features. Lecturers can connect personal Google Drive for file storage. See `docs/SRS-Lectura.md`, `docs/Architecture-Lectura.md`, `docs/Workflow-Lectura.md` for full specs.

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
- **Sign in with Apple** is mobile-only (no web button): the iOS app posts the identity token from
  the native sheet to `POST /api/v1/auth/apple`, which `App\Services\Auth\AppleIdentityToken`
  verifies against `https://appleid.apple.com/auth/keys` (cached a day). No Socialite driver, client
  secret or redirect URI — only `APPLE_CLIENT_IDS` (the bundle id) as the accepted audience.
  - Deleting an account must revoke the Apple token (App Store requirement), and that *does* need a
    signing key: `AppleTokenService` trades the sheet's authorization code for a refresh token at
    sign-in (it expires in minutes, so it cannot wait) and posts it to Apple's revoke endpoint from
    both `Api\V1\AuthController::destroy` and the web `ProfileController::destroy`. Needs
    `APPLE_TEAM_ID`, `APPLE_KEY_ID` and `APPLE_PRIVATE_KEY_PATH` (the .p8); without them nothing is
    exchanged or revoked and sign-in is unaffected. Neither call may block the deletion — a failure
    is reported, never thrown.
  - `users.apple_refresh_token` is an `encrypted` text column and `$hidden`.
- Users table has `google_id` and `apple_id` (both nullable, unique) and `avatar_url` for OAuth
- Password field is nullable to support passwordless Google-only accounts
- **Onboarding** (`/onboarding`): new users with no tenant see role selection page
  - Choose existing institution from dropdown OR create a new one by typing name
  - Select role: Lecturer or Student (visual card selection)
  - Students can enter section invite code for auto-enrollment
  - First lecturer to create an institution gets admin role
  - `OnboardingController` handles both flows
- After login, users redirect to their first active tenant dashboard; if none, to onboarding

### Routing

- **Web** — server-rendered Blade only (`routes/web.php`); no JSON routes for the web UI itself
- **Mobile API** — `routes/api.php` requires one file per area from `routes/api/v1/` (`common`, `student`,
  `lecturer`, `live`, `workspace`); controllers in `App\Http\Controllers\Api\V1\`, Sanctum bearer tokens,
  tenant-scoped under `/api/v1/t/{tenant}` via the `api.tenant` middleware. It serves the Lectura Go
  Flutter app in the parent directory — contracts in `docs/mobile-api/*.md`, conventions in `../CLAUDE.md`
- Auth routes in `routes/auth.php` (Breeze + Google OAuth)
- Tenant routes use `{tenant:slug}` prefix with middleware group: `auth`, `tenant`, `tenant.access`, `locale`
- Route names prefixed with `tenant.` (e.g., `tenant.courses.index`, `tenant.assignments.show`)
- Admin routes under `/admin` prefix with inline super_admin check
- Google Drive callback at `/settings/drive/callback` (outside tenant prefix, Google redirects here directly)

### Course Context (Netflix-style course picker)

- Lecturers pick a course once and the whole lecturer UI follows it. `App\Services\Course\CourseContextService` (singleton) stores the course id per tenant in `session('course_context_{tenantId}')`, with the same access rules as `AuthorizesCourseAccess` (other tenant → 404, not owner/section lecturer/admin → 403)
- `ResolveCourseContext` middleware (alias `course.context`, in the tenant web group after `tenant.access`) binds `current_course`, shares `currentCourse` and `accessibleCoursesForSwitcher` with views, auto-selects a lecturer's only course, and skips students. Any route with a `{course}` parameter the user may open becomes the context, so the sidebar never shows a different course from the page
- `/dashboard` for a lecturer: course selected → redirect to its course overview (`tenant.courses.show`); several courses and none selected → the full-screen picker at `/choose-course` (`CourseContextController@picker`, `tenant/course-context/picker.blade.php`); no courses → the plain dashboard. There is no separate course home page — the course overview is the course landing page
- Select/clear via `POST /course-context` and `/course-context/clear`. The `redirect` field only accepts relative paths (open-redirect guard), so build it with `route(..., false)`; `CourseContextService::switchUrl()` keeps the lecturer on the same tool when switching (same route for the new course when the route's only parameters are tenant + course, else the new course's overview)
- Flat feature indexes (attendance, quizzes, materials, files, portfolio, performance, active learning, assessments, whiteboards) redirect into the context course via the `RedirectsToCourseContext` trait
- UI: course switcher is one command-palette modal (`layouts/partials/course-switcher.blade.php`) opened from the sidebar course card, the topbar pill, or Cmd/Ctrl+K (`open-course-switcher` window event). With a course selected, the sidebar is that course's menu (Teaching / Classroom / Assess / Records); `<x-sidebar-link>` renders its items
- Each course has a stable colour identity from `App\View\CourseAccent` (palette keyed by course id) rendered through `<x-course-avatar :course size="xs|sm|md|lg|xl">`. `tailwind.config.js` scans `app/View/**` so those classes are generated
- Dark mode's Dimmed palette overrides `.bg-white`; on coloured surfaces that must stay white use `bg-[#fff]`

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

- Weekly-organized material system (separate from folder-based Course Files): `CourseMaterialSection` rows (e.g. "Week 1") hold `CourseFile` records via `material_section_id`
- `CourseFile.material_type`: `file` (on the `uploads` disk), `drive` (uploader has Google Drive connected — web uploads go to Drive instead of object storage), or `link`
- Students see a read-only weekly accordion of their enrolled courses
- **View and Download**: `tenant.materials.view` (PDFs/images, `CourseFile::isPreviewable()`, opens inline in a new tab) and `tenant.materials.download` share `CourseMaterialController::serveFile()`. It checks the file belongs to the course and the user teaches or is enrolled, then redirects to a 30-minute signed `temporaryUrl()` with `ResponseContentType`/`ResponseContentDisposition` overrides, so the file comes straight from object storage instead of streaming through PHP. Disks without temporary URLs fall back to `response()`/`download()`
- The mobile API (`Api\V1\Student\MaterialController@download`) still streams through PHP: some HTTP clients forward the bearer token on redirect, which S3 rejects
- Routes: `/materials` (lecturer), `/my-materials` (student)

### File Storage

- User uploads use the `uploads` disk (`config/filesystems.php`): `UPLOADS_DISK=contabo` makes it Contabo S3 object storage (bucket private, `sin1.contabostorage.com`); anything else keeps the private local disk (tests, offline dev). The `media` disk is the public counterpart
- Serve private files with a short-lived `temporaryUrl()` redirect rather than `Storage::download()` where the client is a browser

### Teaching Plan (removed)

- The Teaching Plan page, its routes, controller and AI generator were removed because they duplicated the weekly topics on the course overview and Active Learning activities. The `teaching_plans` / `teaching_plan_weeks` tables, models and existing data are kept but nothing reads them

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

### Assessments & OBE (Outcome-Based Education)

- Separate from Assignments: `Assessment` is the graded-assessment / Course Assessment Plan (CAP) system, controllers in `Tenant\Assessment\`
- **Hierarchy**: assessments are self-referential via `parent_id` — a parent CAP row can hold child components (`children()` ordered by `sort_order`). `isParent()`/`isChild()`, `topLevel()` scope. Parent grading indicators aggregate child grading status
- **CLO/PLO mapping**: `CourseLearningOutcome` (CLO) ↔ `ProgrammeLearningOutcome` (PLO) many-to-many via `clo_plo_mappings`; assessments map to CLOs via `assessment_clos`. Edit CLO→PLO mapping at `/courses/{course}/clo-plo`; manage PLOs, `Programme`, `Faculty`, `AcademicTerm` as institution structure
- **Scoring**: `AssessmentScore` (per-student), `AssessmentItem` (question/rubric line items), `AssessmentCloScore` (per-CLO attainment). `AssessmentScoreService` computes scores; supports manual entry and compute-from-items. Scores are released/unreleased to students (gated visibility)
- **Submissions**: `AssessmentSubmission` + `AssessmentSubmissionFile`; lecturers mark, annotate (image annotations via `SubmissionAnnotationController`), and `SubmissionReportStampingService` stamps report PDFs
- Assessment reports/exports at `/courses/{course}/assessment-reports`; student view at `/my-assessments`

### Student Groups & Group Workspace

- `StudentGroupSet` → `StudentGroup` → `StudentGroupMember` — lecturer creates group sets per course, manual or `arrange-random`. Assessments can bind to a `student_group_set_id` for group grading
- **Workspace** (`/workspace`, `Tenant\Workspace\` controllers): student-facing collaborative group space — chat (`StudentGroupPost`), files/folders (`StudentGroupFile`/`StudentGroupFolder`), meeting minutes (`GroupMinute`), tasks (`GroupTask`), peer voting (`GroupVote`/`GroupVoteRound`), member swap requests (`GroupSwapRequest`), and sleeping-partner reports (`GroupSleepingPartnerReport`)

### Live Session Hub & Whiteboards

- **Live Hub** (`/live`, `StudentSessionController`): students join active-learning sessions by code, respond to polls/questions live, and review afterward. Complements the quiz `QuizSession` real-time flow
- **Whiteboards** (`/whiteboards`, `WhiteboardController`): per-course collaborative whiteboard; scene persisted as JSON in `Whiteboard.scene_data`, saved via PUT `.../scene`

### Performance & Portfolio

- **Performance** (`/performance` lecturer, `/my-performance` student): `PerformanceAggregatorService` rolls up marks/attendance/assessment data into dashboards. AI suggestions (Pro) via `GeneratePerformanceSuggestions` job + `PerformanceAiService`, producing `PerformanceAiSuggestion` records (Alpine polls `ai-status`). The old `/analytics` routes now redirect here
- **Portfolio** (`/portfolio`): lecturers store `PortfolioPhoto` evidence per course (compliance/teaching-portfolio documentation)

### Role Switching (multi-role users)

- Distinct from admin impersonation: a user holding multiple roles in one tenant (e.g. lecturer + coordinator) switches active view via `RoleSwitchController` (`/switch-role`). Chosen role stored in `session('tenant_{id}_role')`; verified against active `tenant_users` rows. `super_admin` may switch to any role

### MCP Server (Lectura as an MCP endpoint)

- The app **exposes** its own MCP server at `POST /mcp` (`McpController` → `App\Services\Mcp\McpServer`), giving MCP clients file/database/Artisan access to the project. Protocol version `2024-11-05`, Bearer-token auth, no CSRF
- Full OAuth 2.0 flow for MCP clients via `McpOAuthController`: RFC 9728/8414 discovery at `/.well-known/oauth-*`, plus `/authorize`, `/oauth/token`, `/oauth/register` (dynamic client registration)

### Console Commands

- `php artisan quizzes:award-full-marks {course} [--quiz=*] [--dry-run] [--force]` — awards full marks to enrolled students for a course's quizzes. Note: CLI has no bound tenant, so it uses `withoutGlobalScopes()` to bypass `BelongsToTenant` — replicate this pattern in any new command touching tenant-scoped models

### Exports

- Excel exports via `maatwebsite/excel` in `app/Exports/` (e.g. `CourseAttendanceExport` with summary + detail sheets). Notifications in `app/Notifications/` cover assessment/assignment/attendance/feedback events

## Conventions

### Code Style
- PSR-12 with `declare(strict_types=1)` in all PHP files
- Models use `BelongsToTenant` trait for tenant-scoped tables
- Controllers use Form Requests for validation (`{Action}{Model}Request`)
- Policies for authorization on all resource controllers
- Activity logging via `spatie/laravel-activitylog` on key models

### Naming
- Models: singular PascalCase (`Course`, `CourseTopic`, `ActiveLearningPlan`)
- Controllers: `{Model}Controller` (tenant controllers in `Tenant\` namespace, sub-features in `Tenant\{Feature}\`)
- Jobs: verb-based (`GenerateActiveLearningPlan`, `ArrangeGroupsWithAi`, `GeneratePerformanceSuggestions`)
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

# Object storage for uploads (Contabo S3)
UPLOADS_DISK=contabo
CONTABO_SPACES_KEY=
CONTABO_SPACES_SECRET=
CONTABO_SPACES_ENDPOINT=
CONTABO_SPACES_BUCKET=lectura
CONTABO_SPACES_CDN_URL=

# AI Providers
AI_DEFAULT_PROVIDER=claude
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=
```
