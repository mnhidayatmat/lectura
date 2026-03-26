# System Architecture & Database Design — Lectura

**AI-Powered Teaching Management PWA**

Version: 1.0
Date: 2026-03-26
Status: Draft
Companion to: SRS-Lectura.md v1.0

---

## Table of Contents

1. [System Architecture Overview](#1-system-architecture-overview)
2. [Multi-Tenancy Strategy](#2-multi-tenancy-strategy)
3. [Laravel Application Structure](#3-laravel-application-structure)
4. [Database Schema](#4-database-schema)
5. [AI Service Architecture](#5-ai-service-architecture)
6. [Real-Time Architecture](#6-real-time-architecture)
7. [Google Drive Integration Architecture](#7-google-drive-integration-architecture)
8. [Queue & Job Architecture](#8-queue--job-architecture)
9. [Authentication & Authorization](#9-authentication--authorization)
10. [API Route Structure](#10-api-route-structure)
11. [PWA Architecture](#11-pwa-architecture)
12. [Internationalisation](#12-internationalisation)
13. [Key Design Decisions](#13-key-design-decisions)

---

## 1. System Architecture Overview

### 1.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENTS                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │ Lecturer PWA │  │ Student PWA  │  │ Admin Dashboard      │  │
│  │ (Desktop)    │  │ (Mobile)     │  │ (Desktop)            │  │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬───────────┘  │
└─────────┼──────────────────┼────────────────────┼───────────────┘
          │                  │                    │
          ▼                  ▼                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                     LOAD BALANCER / REVERSE PROXY               │
│                         (Nginx / Caddy)                         │
└─────────────────────────┬───────────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────────┐
│  Laravel Web │ │  Laravel API │ │  Laravel Reverb   │
│  Server      │ │  Server      │ │  WebSocket Server │
│  (Blade SSR) │ │  (Sanctum)   │ │  (Real-time)      │
└──────┬───────┘ └──────┬───────┘ └──────┬───────────┘
       │                │                │
       └────────────────┼────────────────┘
                        │
          ┌─────────────┼─────────────┐
          ▼             ▼             ▼
┌──────────────┐ ┌────────────┐ ┌────────────────┐
│  MySQL 8.0+  │ │   Redis    │ │ Laravel Queue  │
│  (Primary DB)│ │ (Cache,    │ │  Workers       │
│              │ │  Sessions, │ │ (AI, Drive,    │
│              │ │  Broadcast)│ │  Email, Export) │
└──────────────┘ └────────────┘ └───────┬────────┘
                                        │
                          ┌─────────────┼─────────────┐
                          ▼             ▼             ▼
                 ┌──────────────┐ ┌──────────┐ ┌──────────────┐
                 │ AI Providers │ │ Google   │ │ Mail (SMTP / │
                 │ (Claude /    │ │ Drive    │ │  Mailgun /   │
                 │  OpenAI /    │ │ API      │ │  SES)        │
                 │  Gemini)     │ │          │ │              │
                 └──────────────┘ └──────────┘ └──────────────┘
```

### 1.2 Technology Decisions

| Component | Choice | Rationale |
|---|---|---|
| Framework | Laravel 12 | Modern PHP, excellent ecosystem, built-in auth/queue/broadcast |
| Database | MySQL 8.0+ | JSON columns for flexible data, good Laravel support, scalable |
| Cache | Redis | Session, cache, queue broker, broadcast driver |
| WebSocket | Laravel Reverb | Native Laravel WebSocket, no third-party dependency |
| Frontend | Blade + Tailwind + Alpine.js | Server-rendered for SEO/speed, Alpine for interactivity |
| Real-time UI | Livewire 3 | Live quiz, attendance — reactive without full SPA |
| PDF Generation | DomPDF or Snappy | Teaching plan and report exports |
| Excel Export | Laravel Excel (Maatwebsite) | Attendance and report exports |
| QR Code | Simple QrCode (BaconQrCode) | Server-side QR generation |
| QR Scanning | html5-qrcode (JS) | Client-side camera-based scanning |
| i18n | Laravel Localization | Built-in translation with JSON files |
| Auth | Laravel Breeze + Sanctum | Email/password + API tokens + Google Socialite |

---

## 2. Multi-Tenancy Strategy

### 2.1 Approach: Single Database, Tenant Column

All tenant-scoped tables include a `tenant_id` foreign key. This is the simplest approach for MVP scale (20 tenants, 1,000 users) and avoids multi-database complexity.

### 2.2 Tenant Scoping

```php
// Trait applied to all tenant-scoped models
trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        // Auto-scope all queries to current tenant
        static::addGlobalScope('tenant', function ($query) {
            if ($tenantId = app('current_tenant')?->id) {
                $query->where('tenant_id', $tenantId);
            }
        });

        // Auto-assign tenant_id on creation
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $model->tenant_id = app('current_tenant')?->id;
            }
        });
    }
}
```

### 2.3 Tenant Resolution

Tenant is resolved via:
1. **Subdomain** (preferred): `university-a.lectura.app`
2. **Path prefix** (fallback): `lectura.app/t/university-a`
3. **Header** (API): `X-Tenant-ID` for API clients

Middleware resolves tenant early in the request lifecycle and binds it to the service container.

### 2.4 Tenant-Scoped vs Global Tables

| Global (no tenant_id) | Tenant-Scoped (has tenant_id) |
|---|---|
| `users` | `tenant_users` (pivot) |
| `tenants` | `courses` |
| `tenant_settings` | `sections` |
| `global_folder_templates` | All other domain tables |
| `ai_providers` | |

Users are global (one account, multiple tenants). The `tenant_users` pivot table assigns roles per tenant.

---

## 3. Laravel Application Structure

### 3.1 Domain-Driven Directory Layout

```
app/
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── TenantUser.php
│   ├── Faculty.php
│   ├── Programme.php
│   ├── Course.php
│   ├── Section.php
│   ├── SectionStudent.php
│   ├── AcademicTerm.php
│   ├── CourseLearningOutcome.php
│   ├── TeachingPlan.php
│   ├── TeachingPlanWeek.php
│   ├── TeachingPlanVersion.php
│   ├── Activity.php
│   ├── AttendanceSession.php
│   ├── AttendanceRecord.php
│   ├── QuizSession.php
│   ├── Question.php
│   ├── QuestionOption.php
│   ├── QuizParticipant.php
│   ├── QuizResponse.php
│   ├── Assignment.php
│   ├── RubricCriteria.php
│   ├── RubricLevel.php
│   ├── Submission.php
│   ├── SubmissionFile.php
│   ├── MarkingSuggestion.php
│   ├── StudentMark.php
│   ├── Feedback.php
│   ├── CourseFolder.php
│   ├── CourseFile.php
│   ├── FileTag.php
│   ├── FolderTemplate.php
│   ├── ComplianceChecklist.php
│   ├── ComplianceChecklistItem.php
│   ├── DriveConnection.php
│   ├── Notification.php
│   └── AiUsageLog.php
│
├── Services/
│   ├── Tenant/
│   │   └── TenantResolver.php
│   ├── AI/
│   │   ├── AiServiceManager.php        # Provider-agnostic manager
│   │   ├── Contracts/
│   │   │   └── AiProviderInterface.php
│   │   ├── Providers/
│   │   │   ├── ClaudeProvider.php
│   │   │   ├── OpenAiProvider.php
│   │   │   └── GeminiProvider.php
│   │   ├── TeachingPlannerService.php
│   │   ├── MarkingAssistantService.php
│   │   ├── FeedbackGeneratorService.php
│   │   └── ActivitySuggestionService.php
│   ├── Attendance/
│   │   ├── QrCodeService.php
│   │   └── AttendanceService.php
│   ├── Quiz/
│   │   └── LiveQuizService.php
│   ├── Assignment/
│   │   ├── SubmissionService.php
│   │   └── MarkingService.php
│   ├── CourseFile/
│   │   ├── FolderService.php
│   │   ├── FileTagService.php
│   │   └── ComplianceService.php
│   ├── Drive/
│   │   └── GoogleDriveService.php
│   └── Export/
│       ├── PdfExportService.php
│       └── ExcelExportService.php
│
├── Http/
│   ├── Middleware/
│   │   ├── ResolveTenant.php
│   │   ├── EnsureTenantAccess.php
│   │   └── SetLocale.php
│   ├── Controllers/
│   │   ├── Auth/
│   │   ├── Dashboard/
│   │   ├── Course/
│   │   ├── Section/
│   │   ├── TeachingPlan/
│   │   ├── Attendance/
│   │   ├── Quiz/
│   │   ├── Assignment/
│   │   ├── Marking/
│   │   ├── CourseFile/
│   │   ├── Drive/
│   │   ├── Admin/
│   │   └── Api/
│   └── Requests/
│       └── (Form Request classes per controller)
│
├── Jobs/
│   ├── AI/
│   │   ├── GenerateTeachingPlan.php
│   │   ├── ProcessAiMarking.php
│   │   ├── GenerateFeedback.php
│   │   └── SuggestActivities.php
│   ├── Drive/
│   │   ├── SyncFileToDrive.php
│   │   └── CreateDriveFolders.php
│   ├── Export/
│   │   ├── ExportAttendancePdf.php
│   │   └── ExportTeachingPlanPdf.php
│   └── Import/
│       └── ImportStudentCsv.php
│
├── Events/
│   ├── QuizQuestionBroadcast.php
│   ├── QuizResponseReceived.php
│   ├── AttendanceSessionStarted.php
│   ├── AttendanceCheckedIn.php
│   └── MarkingCompleted.php
│
├── Policies/
│   ├── CoursePolicy.php
│   ├── SectionPolicy.php
│   ├── AssignmentPolicy.php
│   ├── SubmissionPolicy.php
│   └── CourseFolderPolicy.php
│
└── Traits/
    ├── BelongsToTenant.php
    └── HasTags.php
```

---

## 4. Database Schema

### 4.1 Naming Conventions

- Table names: plural snake_case
- Primary keys: `id` (unsigned bigint, auto-increment)
- Foreign keys: `{singular_table}_id`
- Timestamps: `created_at`, `updated_at`
- Soft deletes: `deleted_at` where applicable
- All tenant-scoped tables have `tenant_id` as first foreign key after `id`
- Indexes on all foreign keys and frequently queried columns

### 4.2 Core Platform Tables

#### `tenants`

```sql
CREATE TABLE tenants (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(100) NOT NULL UNIQUE,          -- subdomain/path identifier
    logo_url        VARCHAR(500) NULL,
    primary_color   VARCHAR(7) NULL,                       -- hex color for branding
    timezone        VARCHAR(50) DEFAULT 'Asia/Kuala_Lumpur',
    locale          VARCHAR(10) DEFAULT 'en',              -- default language
    is_active       BOOLEAN DEFAULT TRUE,
    settings        JSON NULL,                             -- flexible tenant config
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL
);
```

**`settings` JSON structure:**
```json
{
    "auth": {
        "allow_google_login": true,
        "sso_enabled": false,
        "sso_provider": null
    },
    "ai": {
        "enabled": true,
        "provider": "claude",
        "api_key_source": "platform",
        "custom_api_key": null,
        "modules_enabled": ["teaching_plan", "marking", "feedback", "activity"],
        "monthly_quota": 10000
    },
    "storage": {
        "drive_mode": "lecturer",
        "max_file_size_mb": 25
    },
    "privacy": {
        "ai_consent_required": true,
        "data_retention_months": 36,
        "admin_can_view_individual_marks": false
    }
}
```

#### `users`

```sql
CREATE TABLE users (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    email               VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at   TIMESTAMP NULL,
    password            VARCHAR(255) NULL,                 -- nullable for social login
    google_id           VARCHAR(255) NULL UNIQUE,
    avatar_url          VARCHAR(500) NULL,
    locale              VARCHAR(10) DEFAULT 'en',          -- user language preference
    is_super_admin      BOOLEAN DEFAULT FALSE,
    remember_token      VARCHAR(100) NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    deleted_at          TIMESTAMP NULL
);
```

#### `tenant_users`

```sql
CREATE TABLE tenant_users (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    role        ENUM('admin', 'coordinator', 'lecturer', 'student') NOT NULL,
    student_id_number VARCHAR(50) NULL,                    -- institutional student ID
    is_active   BOOLEAN DEFAULT TRUE,
    joined_at   TIMESTAMP NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    UNIQUE KEY uq_tenant_user_role (tenant_id, user_id, role),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 4.3 Academic Structure Tables

#### `faculties`

```sql
CREATE TABLE faculties (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(255) NOT NULL,
    code        VARCHAR(20) NULL,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

#### `programmes`

```sql
CREATE TABLE programmes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    faculty_id  BIGINT UNSIGNED NULL,
    name        VARCHAR(255) NOT NULL,
    code        VARCHAR(20) NULL,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL
);
```

#### `academic_terms`

```sql
CREATE TABLE academic_terms (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,                     -- e.g. "Semester 1 2026/2027"
    code        VARCHAR(20) NULL,                          -- e.g. "SEM1-2627"
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    is_default  BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

### 4.4 Course & Section Tables

#### `courses`

```sql
CREATE TABLE courses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    faculty_id      BIGINT UNSIGNED NULL,
    programme_id    BIGINT UNSIGNED NULL,
    academic_term_id BIGINT UNSIGNED NULL,
    lecturer_id     BIGINT UNSIGNED NOT NULL,              -- primary lecturer (user_id)
    code            VARCHAR(20) NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    credit_hours    TINYINT UNSIGNED NULL,
    num_weeks       TINYINT UNSIGNED DEFAULT 14,
    teaching_mode   ENUM('face_to_face', 'online', 'hybrid') DEFAULT 'face_to_face',
    format          JSON NULL,                             -- {"lecture": true, "tutorial": true, "lab": false}
    status          ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    -- Custom academic term override
    custom_start_date DATE NULL,
    custom_end_date   DATE NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,

    INDEX idx_courses_tenant_term (tenant_id, academic_term_id),
    INDEX idx_courses_lecturer (lecturer_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
    FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL,
    FOREIGN KEY (academic_term_id) REFERENCES academic_terms(id) ON DELETE SET NULL,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `course_learning_outcomes`

```sql
CREATE TABLE course_learning_outcomes (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   BIGINT UNSIGNED NOT NULL,
    code        VARCHAR(20) NOT NULL,                      -- e.g. "CLO1", "CLO2"
    description TEXT NOT NULL,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
```

#### `course_topics`

```sql
CREATE TABLE course_topics (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   BIGINT UNSIGNED NOT NULL,
    week_number TINYINT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT NULL,
    clo_ids     JSON NULL,                                 -- [1, 3] referencing CLO ids
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    INDEX idx_course_topics_week (course_id, week_number),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
```

#### `sections`

```sql
CREATE TABLE sections (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    course_id   BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(50) NOT NULL,                      -- e.g. "Section 01"
    code        VARCHAR(20) NOT NULL,                      -- display code
    invite_code VARCHAR(10) NOT NULL UNIQUE,               -- for student self-enrollment
    capacity    SMALLINT UNSIGNED NULL,
    schedule    JSON NULL,                                 -- {"day": "Monday", "time": "09:00", "room": "DK1"}
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    INDEX idx_sections_course (course_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
```

#### `section_students`

```sql
CREATE TABLE section_students (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP NULL,
    enrollment_method ENUM('csv', 'manual', 'invite_code') DEFAULT 'manual',
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    UNIQUE KEY uq_section_student (section_id, user_id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 4.5 Teaching Plan Tables

#### `teaching_plans`

```sql
CREATE TABLE teaching_plans (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   BIGINT UNSIGNED NOT NULL,
    version     INT UNSIGNED DEFAULT 1,
    status      ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_by  BIGINT UNSIGNED NOT NULL,
    change_note TEXT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    INDEX idx_teaching_plans_course (course_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `teaching_plan_weeks`

```sql
CREATE TABLE teaching_plan_weeks (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teaching_plan_id    BIGINT UNSIGNED NOT NULL,
    week_number         TINYINT UNSIGNED NOT NULL,
    topic_id            BIGINT UNSIGNED NULL,
    lesson_flow         TEXT NULL,                          -- structured lesson plan text
    duration_minutes    SMALLINT UNSIGNED NULL,
    active_learning     JSON NULL,                         -- AI-suggested activities
    online_alternatives JSON NULL,
    formative_checks    JSON NULL,
    time_allocation     JSON NULL,                         -- {"intro": 10, "activity": 30, "wrap": 10}
    assessment_notes    TEXT NULL,
    ai_generated        BOOLEAN DEFAULT FALSE,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    INDEX idx_plan_weeks (teaching_plan_id, week_number),
    FOREIGN KEY (teaching_plan_id) REFERENCES teaching_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES course_topics(id) ON DELETE SET NULL
);
```

#### `activities`

```sql
CREATE TABLE activities (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teaching_plan_week_id BIGINT UNSIGNED NULL,
    course_id           BIGINT UNSIGNED NOT NULL,
    type                ENUM('think_pair_share', 'case_discussion', 'problem_based',
                             'mini_debate', 'concept_mapping', 'scenario_analysis',
                             'group_worksheet', 'peer_teaching', 'reflection',
                             'other') NOT NULL,
    title               VARCHAR(255) NOT NULL,
    description         TEXT NULL,
    instructions        TEXT NULL,
    duration_minutes    SMALLINT UNSIGNED NULL,
    delivery_mode       ENUM('physical', 'online', 'hybrid') DEFAULT 'physical',
    materials           JSON NULL,                         -- links, resources
    ai_generated        BOOLEAN DEFAULT FALSE,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (teaching_plan_week_id) REFERENCES teaching_plan_weeks(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
```

### 4.6 Attendance Tables

#### `attendance_sessions`

```sql
CREATE TABLE attendance_sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    section_id      BIGINT UNSIGNED NOT NULL,
    lecturer_id     BIGINT UNSIGNED NOT NULL,
    session_type    ENUM('lecture', 'tutorial', 'lab', 'extra', 'replacement') NOT NULL,
    week_number     TINYINT UNSIGNED NULL,
    qr_secret       VARCHAR(64) NOT NULL,                  -- current QR token
    qr_mode         ENUM('rotating', 'fixed') DEFAULT 'rotating',
    qr_rotation_seconds SMALLINT UNSIGNED DEFAULT 30,
    late_threshold_minutes SMALLINT UNSIGNED DEFAULT 15,
    status          ENUM('active', 'ended') DEFAULT 'active',
    started_at      TIMESTAMP NOT NULL,
    ended_at        TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    INDEX idx_attendance_section (section_id, started_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `attendance_records`

```sql
CREATE TABLE attendance_records (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attendance_session_id   BIGINT UNSIGNED NOT NULL,
    user_id                 BIGINT UNSIGNED NOT NULL,
    status                  ENUM('present', 'late', 'absent', 'excused') DEFAULT 'present',
    checked_in_at           TIMESTAMP NULL,
    method                  ENUM('qr_scan', 'manual') DEFAULT 'qr_scan',
    device_info             JSON NULL,                     -- user agent, IP (for fraud logging)
    override_by             BIGINT UNSIGNED NULL,          -- lecturer who overrode
    override_reason         VARCHAR(255) NULL,
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,

    UNIQUE KEY uq_attendance_record (attendance_session_id, user_id),
    FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (override_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 4.7 Live Quiz Tables

#### `quiz_sessions`

```sql
CREATE TABLE quiz_sessions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    section_id  BIGINT UNSIGNED NOT NULL,
    lecturer_id BIGINT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    join_code   VARCHAR(8) NOT NULL UNIQUE,
    mode        ENUM('formative', 'participation', 'graded') DEFAULT 'formative',
    is_anonymous BOOLEAN DEFAULT FALSE,
    status      ENUM('waiting', 'active', 'reviewing', 'ended') DEFAULT 'waiting',
    settings    JSON NULL,                                 -- timer defaults, etc.
    started_at  TIMESTAMP NULL,
    ended_at    TIMESTAMP NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    INDEX idx_quiz_section (section_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `questions`

```sql
CREATE TABLE questions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    course_id       BIGINT UNSIGNED NULL,                  -- NULL = bank-level question
    question_type   ENUM('mcq', 'true_false', 'short_answer') NOT NULL,
    text            TEXT NOT NULL,
    explanation     TEXT NULL,                              -- correct answer explanation
    difficulty      ENUM('easy', 'medium', 'hard') NULL,
    time_limit_seconds SMALLINT UNSIGNED DEFAULT 30,
    points          DECIMAL(5,2) DEFAULT 1.00,
    tags            JSON NULL,                             -- ["topic:algorithms", "clo:CLO2"]
    is_bank         BOOLEAN DEFAULT TRUE,                  -- saved to question bank
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    INDEX idx_questions_bank (tenant_id, created_by, is_bank),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);
```

#### `question_options`

```sql
CREATE TABLE question_options (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    label       VARCHAR(10) NOT NULL,                      -- "A", "B", "C", "D"
    text        TEXT NOT NULL,
    is_correct  BOOLEAN DEFAULT FALSE,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);
```

#### `quiz_session_questions`

```sql
CREATE TABLE quiz_session_questions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_session_id BIGINT UNSIGNED NOT NULL,
    question_id     BIGINT UNSIGNED NOT NULL,
    sort_order      INT DEFAULT 0,
    status          ENUM('pending', 'active', 'closed') DEFAULT 'pending',
    opened_at       TIMESTAMP NULL,
    closed_at       TIMESTAMP NULL,

    FOREIGN KEY (quiz_session_id) REFERENCES quiz_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);
```

#### `quiz_participants`

```sql
CREATE TABLE quiz_participants (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_session_id BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    display_name    VARCHAR(100) NULL,                     -- anonymous alias if needed
    total_score     DECIMAL(8,2) DEFAULT 0,
    joined_at       TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    UNIQUE KEY uq_quiz_participant (quiz_session_id, user_id),
    FOREIGN KEY (quiz_session_id) REFERENCES quiz_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `quiz_responses`

```sql
CREATE TABLE quiz_responses (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_session_question_id BIGINT UNSIGNED NOT NULL,
    quiz_participant_id     BIGINT UNSIGNED NOT NULL,
    answer_text             TEXT NULL,                      -- for short answer
    selected_option_id      BIGINT UNSIGNED NULL,           -- for MCQ/TF
    is_correct              BOOLEAN NULL,
    points_earned           DECIMAL(5,2) DEFAULT 0,
    response_time_ms        INT UNSIGNED NULL,              -- how fast they answered
    created_at              TIMESTAMP NULL,
    updated_at              TIMESTAMP NULL,

    UNIQUE KEY uq_quiz_response (quiz_session_question_id, quiz_participant_id),
    FOREIGN KEY (quiz_session_question_id) REFERENCES quiz_session_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_participant_id) REFERENCES quiz_participants(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE SET NULL
);
```

### 4.8 Assignment & Marking Tables

#### `assignments`

```sql
CREATE TABLE assignments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    course_id       BIGINT UNSIGNED NOT NULL,
    section_id      BIGINT UNSIGNED NULL,                  -- NULL = all sections
    created_by      BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    type            ENUM('individual', 'group') DEFAULT 'individual',
    total_marks     DECIMAL(8,2) NOT NULL,
    deadline        TIMESTAMP NULL,
    late_deadline   TIMESTAMP NULL,                        -- grace period
    allow_resubmission BOOLEAN DEFAULT FALSE,
    max_resubmissions TINYINT UNSIGNED DEFAULT 0,
    marking_mode    ENUM('manual', 'ai_assisted') DEFAULT 'manual',
    answer_scheme   TEXT NULL,                             -- text-based answer scheme
    answer_scheme_file_id BIGINT UNSIGNED NULL,            -- uploaded file reference
    status          ENUM('draft', 'published', 'closed', 'graded') DEFAULT 'draft',
    clo_ids         JSON NULL,                             -- linked CLOs
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,

    INDEX idx_assignments_course (course_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `rubrics`

```sql
CREATE TABLE rubrics (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id   BIGINT UNSIGNED NOT NULL,
    type            ENUM('matrix', 'free_text') DEFAULT 'matrix',
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
);
```

#### `rubric_criteria`

```sql
CREATE TABLE rubric_criteria (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rubric_id   BIGINT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT NULL,
    max_marks   DECIMAL(5,2) NOT NULL,
    sort_order  INT DEFAULT 0,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (rubric_id) REFERENCES rubrics(id) ON DELETE CASCADE
);
```

#### `rubric_levels`

```sql
CREATE TABLE rubric_levels (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rubric_criteria_id  BIGINT UNSIGNED NOT NULL,
    label               VARCHAR(100) NOT NULL,             -- "Excellent", "Good", "Poor"
    description         TEXT NULL,
    marks               DECIMAL(5,2) NOT NULL,
    sort_order          INT DEFAULT 0,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    FOREIGN KEY (rubric_criteria_id) REFERENCES rubric_criteria(id) ON DELETE CASCADE
);
```

#### `assignment_groups`

```sql
CREATE TABLE assignment_groups (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id   BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
);
```

#### `assignment_group_members`

```sql
CREATE TABLE assignment_group_members (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_group_id BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP NULL,

    UNIQUE KEY uq_group_member (assignment_group_id, user_id),
    FOREIGN KEY (assignment_group_id) REFERENCES assignment_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### `submissions`

```sql
CREATE TABLE submissions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id       BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED NULL,              -- NULL for group submission
    assignment_group_id BIGINT UNSIGNED NULL,              -- for group submission
    submission_number   TINYINT UNSIGNED DEFAULT 1,        -- resubmission tracking
    notes               TEXT NULL,                         -- student notes
    is_late             BOOLEAN DEFAULT FALSE,
    submitted_at        TIMESTAMP NOT NULL,
    status              ENUM('submitted', 'ai_processing', 'ai_completed',
                             'marking', 'graded') DEFAULT 'submitted',
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,

    INDEX idx_submissions_assignment (assignment_id),
    INDEX idx_submissions_user (user_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assignment_group_id) REFERENCES assignment_groups(id) ON DELETE SET NULL
);
```

#### `submission_files`

```sql
CREATE TABLE submission_files (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id   BIGINT UNSIGNED NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_type       VARCHAR(50) NOT NULL,                  -- "pdf", "image/jpeg"
    file_size_bytes INT UNSIGNED NOT NULL,
    drive_file_id   VARCHAR(255) NULL,                     -- Google Drive file ID
    drive_url       VARCHAR(500) NULL,
    local_path      VARCHAR(500) NULL,                     -- temp path before Drive sync
    status          ENUM('uploading', 'synced', 'failed') DEFAULT 'uploading',
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);
```

#### `marking_suggestions`

```sql
CREATE TABLE marking_suggestions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id   BIGINT UNSIGNED NOT NULL,
    criteria_id     BIGINT UNSIGNED NULL,                  -- rubric criteria reference
    question_ref    VARCHAR(50) NULL,                      -- "Q1", "Q2a" etc.
    extracted_answer TEXT NULL,                             -- AI-extracted student answer
    suggested_marks DECIMAL(5,2) NULL,
    max_marks       DECIMAL(5,2) NULL,
    explanation     TEXT NULL,                              -- why AI gave this mark
    confidence      DECIMAL(3,2) NULL,                     -- 0.00 to 1.00
    status          ENUM('pending', 'accepted', 'modified', 'rejected') DEFAULT 'pending',
    final_marks     DECIMAL(5,2) NULL,                     -- lecturer-confirmed marks
    lecturer_note   TEXT NULL,
    reviewed_by     BIGINT UNSIGNED NULL,
    reviewed_at     TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    INDEX idx_marking_submission (submission_id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (criteria_id) REFERENCES rubric_criteria(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### `student_marks`

```sql
CREATE TABLE student_marks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    assignment_id   BIGINT UNSIGNED NOT NULL,
    submission_id   BIGINT UNSIGNED NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    total_marks     DECIMAL(8,2) NOT NULL,
    max_marks       DECIMAL(8,2) NOT NULL,
    percentage      DECIMAL(5,2) NULL,
    grade           VARCHAR(5) NULL,                       -- "A", "B+", etc.
    is_final        BOOLEAN DEFAULT FALSE,
    finalized_by    BIGINT UNSIGNED NULL,
    finalized_at    TIMESTAMP NULL,
    mark_adjustment DECIMAL(5,2) NULL,                     -- for group member adjustment
    adjustment_reason VARCHAR(255) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    UNIQUE KEY uq_student_mark (assignment_id, user_id),
    INDEX idx_marks_student (user_id, tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (finalized_by) REFERENCES users(id) ON DELETE SET NULL
);
```

#### `feedbacks`

```sql
CREATE TABLE feedbacks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id   BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,              -- student receiving feedback
    strengths       TEXT NULL,
    missing_points  TEXT NULL,
    misconceptions  TEXT NULL,
    revision_advice TEXT NULL,
    improvement_tips TEXT NULL,
    follow_up_suggestions TEXT NULL,
    performance_level ENUM('low', 'average', 'advanced') NULL,
    ai_generated    BOOLEAN DEFAULT FALSE,
    is_released     BOOLEAN DEFAULT FALSE,
    released_at     TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    INDEX idx_feedback_student (user_id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 4.9 Course File Management Tables

#### `folder_templates`

```sql
CREATE TABLE folder_templates (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NULL,                      -- NULL = global/system default
    faculty_id  BIGINT UNSIGNED NULL,
    programme_id BIGINT UNSIGNED NULL,
    created_by  BIGINT UNSIGNED NULL,                      -- lecturer personal template
    name        VARCHAR(255) NOT NULL,
    description TEXT NULL,
    structure   JSON NOT NULL,                             -- nested folder structure
    is_default  BOOLEAN DEFAULT FALSE,
    scope       ENUM('global', 'tenant', 'faculty', 'programme', 'personal') NOT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
    FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

**`structure` JSON example:**
```json
[
    {"name": "Course Information", "children": []},
    {"name": "Teaching Plan", "children": [
        {"name": "Current Version", "children": []},
        {"name": "Previous Versions", "children": []}
    ]},
    {"name": "Weekly Materials", "children": []},
    {"name": "Attendance Records", "children": []},
    {"name": "Active Learning Activities", "children": []},
    {"name": "Quizzes", "children": []},
    {"name": "Assignments", "children": []},
    {"name": "Rubrics and Marking Schemes", "children": []},
    {"name": "Student Submissions", "children": []},
    {"name": "Marked Scripts and Feedback", "children": []},
    {"name": "CLO / Performance Reports", "children": []},
    {"name": "Reflection / CQI", "children": []},
    {"name": "Supporting Evidence", "children": []}
]
```

#### `course_folders`

```sql
CREATE TABLE course_folders (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       BIGINT UNSIGNED NOT NULL,
    parent_id       BIGINT UNSIGNED NULL,                  -- self-referencing for subfolders
    name            VARCHAR(255) NOT NULL,
    sort_order      INT DEFAULT 0,
    drive_folder_id VARCHAR(255) NULL,                     -- Google Drive folder ID
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    INDEX idx_course_folders (course_id, parent_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES course_folders(id) ON DELETE CASCADE
);
```

#### `course_files`

```sql
CREATE TABLE course_files (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_folder_id BIGINT UNSIGNED NOT NULL,
    course_id       BIGINT UNSIGNED NOT NULL,
    uploaded_by     BIGINT UNSIGNED NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_type       VARCHAR(50) NOT NULL,
    file_size_bytes INT UNSIGNED NOT NULL,
    drive_file_id   VARCHAR(255) NULL,
    drive_url       VARCHAR(500) NULL,
    description     TEXT NULL,
    -- Denormalized references for quick filtering
    section_id      BIGINT UNSIGNED NULL,
    week_number     TINYINT UNSIGNED NULL,
    assignment_id   BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    deleted_at      TIMESTAMP NULL,

    INDEX idx_course_files_folder (course_folder_id),
    INDEX idx_course_files_course (course_id),
    FOREIGN KEY (course_folder_id) REFERENCES course_folders(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE SET NULL
);
```

#### `file_tags`

```sql
CREATE TABLE file_tags (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_file_id  BIGINT UNSIGNED NOT NULL,
    tag_type        ENUM('week', 'clo', 'assessment_type', 'section', 'topic',
                         'evidence_type', 'semester', 'activity_type') NOT NULL,
    tag_value       VARCHAR(255) NOT NULL,

    INDEX idx_file_tags (course_file_id),
    INDEX idx_file_tags_lookup (tag_type, tag_value),
    FOREIGN KEY (course_file_id) REFERENCES course_files(id) ON DELETE CASCADE
);
```

#### `compliance_checklists`

```sql
CREATE TABLE compliance_checklists (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    faculty_id  BIGINT UNSIGNED NULL,                      -- NULL = institution-wide
    programme_id BIGINT UNSIGNED NULL,
    name        VARCHAR(255) NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL,
    FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL
);
```

#### `compliance_checklist_items`

```sql
CREATE TABLE compliance_checklist_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checklist_id    BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,                 -- "Course outline uploaded"
    description     TEXT NULL,
    rule_type       ENUM('file_exists', 'tag_exists', 'folder_not_empty', 'custom') NOT NULL,
    rule_config     JSON NULL,                             -- {"folder": "Teaching Plan", "min_files": 1}
    is_required     BOOLEAN DEFAULT TRUE,
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    FOREIGN KEY (checklist_id) REFERENCES compliance_checklists(id) ON DELETE CASCADE
);
```

### 4.10 Google Drive Tables

#### `drive_connections`

```sql
CREATE TABLE drive_connections (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    google_email    VARCHAR(255) NOT NULL,
    access_token    TEXT NOT NULL,                          -- encrypted
    refresh_token   TEXT NOT NULL,                          -- encrypted
    token_expires_at TIMESTAMP NULL,
    root_folder_id  VARCHAR(255) NULL,                     -- Lectura root folder on Drive
    is_active       BOOLEAN DEFAULT TRUE,
    connected_at    TIMESTAMP NULL,
    disconnected_at TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    INDEX idx_drive_user (user_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 4.11 Notification Tables

#### `notifications`

Uses Laravel's built-in `notifications` table with added fields:

```sql
CREATE TABLE notifications (
    id          CHAR(36) PRIMARY KEY,
    type        VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data        JSON NOT NULL,
    read_at     TIMESTAMP NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,

    INDEX idx_notifications_notifiable (notifiable_type, notifiable_id, read_at)
);
```

**`data` JSON structure example:**
```json
{
    "tenant_id": 1,
    "title": "Feedback Available",
    "message": "Your feedback for Assignment 1 (CS101) is ready.",
    "action_url": "/courses/1/assignments/5/feedback",
    "category": "feedback",
    "icon": "comment"
}
```

### 4.12 AI Usage Tracking

#### `ai_usage_logs`

```sql
CREATE TABLE ai_usage_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    course_id       BIGINT UNSIGNED NULL,
    module          ENUM('teaching_plan', 'marking', 'feedback', 'activity') NOT NULL,
    provider        VARCHAR(50) NOT NULL,                  -- "claude", "openai", "gemini"
    model           VARCHAR(100) NOT NULL,                 -- "claude-sonnet-4-6", "gpt-4o"
    input_tokens    INT UNSIGNED DEFAULT 0,
    output_tokens   INT UNSIGNED DEFAULT 0,
    cost_usd        DECIMAL(10,6) NULL,                    -- estimated cost
    request_payload JSON NULL,                             -- optional debug (not in prod)
    response_status ENUM('success', 'failed', 'timeout') NOT NULL,
    duration_ms     INT UNSIGNED NULL,
    created_at      TIMESTAMP NULL,

    INDEX idx_ai_usage_tenant (tenant_id, created_at),
    INDEX idx_ai_usage_module (module, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);
```

### 4.13 Audit Log

#### `audit_logs`

```sql
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NULL,
    user_id     BIGINT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,                     -- "mark.finalized", "attendance.override"
    auditable_type VARCHAR(255) NULL,                      -- model class
    auditable_id BIGINT UNSIGNED NULL,
    old_values  JSON NULL,
    new_values  JSON NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(500) NULL,
    created_at  TIMESTAMP NULL,

    INDEX idx_audit_tenant (tenant_id, created_at),
    INDEX idx_audit_auditable (auditable_type, auditable_id)
);
```

---

## 5. AI Service Architecture

### 5.1 Provider-Agnostic Design

```
┌──────────────────────────────────┐
│       AiServiceManager           │ ← Resolves provider per tenant
│  ┌────────────────────────────┐  │
│  │  AiProviderInterface       │  │
│  │  - complete(prompt, opts)  │  │
│  │  - getModels()             │  │
│  │  - estimateCost(tokens)    │  │
│  └─────────┬──────────────────┘  │
│       ┌────┼────┬────────┐       │
│       ▼    ▼    ▼        ▼       │
│  ┌──────┐┌──────┐┌──────┐┌────┐ │
│  │Claude││OpenAI││Gemini││Local│ │
│  └──────┘└──────┘└──────┘└────┘ │
└──────────────────────────────────┘
          │
          ▼
┌──────────────────────────────────┐
│   Domain-Specific AI Services    │
│                                  │
│  TeachingPlannerService          │
│    → buildPrompt(course, week)   │
│    → parseResponse(raw)          │
│                                  │
│  MarkingAssistantService         │
│    → buildPrompt(submission,     │
│        scheme, rubric)           │
│    → parseMarks(raw)             │
│                                  │
│  FeedbackGeneratorService        │
│    → buildPrompt(marks, level)   │
│    → parseFeedback(raw)          │
│                                  │
│  ActivitySuggestionService       │
│    → buildPrompt(topic, context) │
│    → parseActivities(raw)        │
└──────────────────────────────────┘
```

### 5.2 AI Request Flow

```
1. Lecturer triggers action (e.g., "Generate teaching plan")
2. Controller dispatches Job → Queue (ai-processing)
3. Job resolves tenant's AI provider via AiServiceManager
4. Domain service builds prompt with context
5. Provider sends request to external API
6. Response parsed into structured data
7. Result saved to database (e.g., teaching_plan_weeks)
8. AI usage logged to ai_usage_logs
9. Notification sent to lecturer
10. UI updates via polling or WebSocket
```

### 5.3 Prompt Templates

Stored as Blade views for maintainability:

```
resources/views/ai/prompts/
├── teaching-plan/
│   └── generate-week.blade.php
├── marking/
│   ├── extract-answers.blade.php
│   └── suggest-marks.blade.php
├── feedback/
│   └── generate.blade.php
└── activity/
    └── suggest.blade.php
```

### 5.4 Rate Limiting & Quotas

```php
// Per-tenant monthly quota check
class AiQuotaMiddleware
{
    // Check ai_usage_logs count for current month
    // Compare against tenant settings.ai.monthly_quota
    // Reject with 429 if exceeded
}
```

---

## 6. Real-Time Architecture

### 6.1 Laravel Reverb Configuration

```
Reverb Server (WebSocket)
    ├── Private Channel: tenant.{id}.quiz.{sessionId}
    │   ├── QuestionBroadcast       → new question shown to students
    │   ├── ResponseReceived        → lecturer sees new answer
    │   ├── ResultsBroadcast        → answer distribution update
    │   └── LeaderboardUpdate       → scores update
    │
    ├── Private Channel: tenant.{id}.attendance.{sessionId}
    │   ├── SessionStarted          → students see QR available
    │   ├── CheckedIn               → lecturer sees new check-in
    │   └── SessionEnded            → session closed
    │
    ├── Private Channel: user.{id}.notifications
    │   └── NewNotification         → in-app notification
    │
    └── Presence Channel: tenant.{id}.quiz.{sessionId}.participants
        └── Track who is currently in the quiz session
```

### 6.2 QR Code Rotation

```
1. AttendanceSession created with qr_secret
2. QR encodes: {session_id}:{token}:{timestamp}
3. Server generates new token every 30s via scheduled task or JS timer
4. Student scans → API validates token freshness + session active
5. Grace window: accept token from current OR previous rotation
```

Implementation: QR rotation is handled **client-side** on the lecturer's browser. The lecturer's page generates a new HMAC-based token every 30 seconds using a shared secret. The server validates by recomputing the HMAC within the acceptable time window. No server round-trip needed for rotation.

---

## 7. Google Drive Integration Architecture

### 7.1 Architecture Pattern

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────┐
│  Lectura App    │     │  Queue Worker     │     │ Google Drive │
│                 │     │                   │     │ API          │
│ Upload file ────┼────►│ SyncFileToDrive  ─┼────►│              │
│                 │     │                   │◄────┤ file_id      │
│ Save metadata ◄─┼─────┤ Update metadata   │     │              │
│ (DB)            │     │                   │     │              │
└─────────────────┘     └──────────────────┘     └─────────────┘
```

### 7.2 File Upload Flow

```
1. Student/Lecturer uploads file via app
2. File temporarily stored in local storage (storage/app/temp/)
3. Metadata record created in course_files (status: "uploading")
4. SyncFileToDrive job dispatched to queue
5. Job uploads file to Drive folder
6. Job updates course_files with drive_file_id and drive_url
7. Local temp file deleted
8. Status updated to "synced"
```

### 7.3 Drive Folder Mirroring

When course folders are created/modified in the app, a `SyncDriveFolders` job mirrors the structure to Google Drive:

```
Lecturer's Drive/
└── Lectura/
    └── CS101 - Data Structures (Sem1 2627)/
        ├── Course Information/
        ├── Teaching Plan/
        ├── Weekly Materials/
        └── ... (mirrors course_folders table)
```

### 7.4 Token Refresh

Drive OAuth tokens are encrypted in the database. A scheduled job refreshes tokens before expiry. If refresh fails (revoked), the connection is marked inactive and the lecturer is notified.

---

## 8. Queue & Job Architecture

### 8.1 Queue Connections

| Queue | Purpose | Workers |
|---|---|---|
| `default` | General jobs, CSV import, notifications | 2 |
| `ai-processing` | AI marking, plan generation, feedback | 2 |
| `drive-sync` | Google Drive file uploads and folder sync | 1 |
| `exports` | PDF/Excel generation | 1 |
| `broadcast` | WebSocket event broadcasting | 1 (Reverb) |

### 8.2 Key Jobs

| Job | Queue | Timeout | Retries |
|---|---|---|---|
| `GenerateTeachingPlan` | ai-processing | 120s | 2 |
| `ProcessAiMarking` | ai-processing | 90s | 2 |
| `GenerateFeedback` | ai-processing | 60s | 2 |
| `SuggestActivities` | ai-processing | 60s | 2 |
| `SyncFileToDrive` | drive-sync | 60s | 3 |
| `CreateDriveFolders` | drive-sync | 30s | 3 |
| `ImportStudentCsv` | default | 60s | 1 |
| `ExportAttendancePdf` | exports | 60s | 1 |
| `ExportTeachingPlanPdf` | exports | 60s | 1 |
| `SendNotificationEmail` | default | 30s | 3 |

### 8.3 Failed Job Handling

- Exponential backoff for retries
- Failed jobs logged to `failed_jobs` table
- AI failures: status set to "failed" on the parent record, lecturer notified
- Drive failures: file status set to "failed", retry button in UI

---

## 9. Authentication & Authorization

### 9.1 Auth Stack

```
Laravel Breeze (Blade)     → web session auth (lecturers, admins)
Laravel Sanctum            → API token auth (PWA API calls, student mobile)
Laravel Socialite          → Google OAuth
```

### 9.2 Authorization Model

```
Permissions resolved via: User → TenantUser (role) → Policy

Roles:
  super_admin  → platform-wide (no tenant scope)
  admin        → full tenant management
  coordinator  → view reports, manage checklists, limited course access
  lecturer     → full course management within own courses
  student      → view own data, submit, participate
```

### 9.3 Policy Pattern

```php
class CoursePolicy
{
    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->lecturer_id
            || $user->hasRoleInTenant($course->tenant_id, ['admin']);
    }

    public function viewStudentMarks(User $user, Course $course): bool
    {
        if ($user->hasRoleInTenant($course->tenant_id, ['student'])) {
            // Students can only see own marks (enforced at query level)
            return true;
        }
        return $user->id === $course->lecturer_id
            || $user->hasRoleInTenant($course->tenant_id, ['admin', 'coordinator']);
    }
}
```

### 9.4 Tenant Middleware Stack

```php
// Applied to all tenant-scoped routes
Route::middleware(['auth', 'resolve.tenant', 'ensure.tenant.access', 'set.locale'])
    ->prefix('{tenant:slug}')
    ->group(function () {
        // All tenant routes
    });
```

---

## 10. API Route Structure

### 10.1 Web Routes (Blade SSR)

```
# Auth
GET     /login
POST    /login
GET     /register
GET     /auth/google
GET     /auth/google/callback

# Tenant-scoped web routes: /{tenant:slug}/...
GET     /dashboard

# Courses
GET     /courses
GET     /courses/create
POST    /courses
GET     /courses/{course}
GET     /courses/{course}/edit
PUT     /courses/{course}

# Sections
GET     /courses/{course}/sections
POST    /courses/{course}/sections
GET     /courses/{course}/sections/{section}
GET     /courses/{course}/sections/{section}/students
POST    /courses/{course}/sections/{section}/students/import
POST    /courses/{course}/sections/{section}/students

# Teaching Plans
GET     /courses/{course}/teaching-plan
POST    /courses/{course}/teaching-plan/generate
PUT     /courses/{course}/teaching-plan/weeks/{week}
GET     /courses/{course}/teaching-plan/export/pdf
GET     /courses/{course}/teaching-plan/versions

# Attendance
GET     /courses/{course}/sections/{section}/attendance
POST    /courses/{course}/sections/{section}/attendance/start
PUT     /attendance-sessions/{session}/end
GET     /attendance-sessions/{session}/qr
GET     /courses/{course}/sections/{section}/attendance/export

# Quizzes
GET     /courses/{course}/sections/{section}/quizzes
POST    /courses/{course}/sections/{section}/quizzes
GET     /quiz-sessions/{session}
POST    /quiz-sessions/{session}/questions
PUT     /quiz-sessions/{session}/questions/{question}/activate
PUT     /quiz-sessions/{session}/end

# Assignments
GET     /courses/{course}/assignments
POST    /courses/{course}/assignments
GET     /courses/{course}/assignments/{assignment}
GET     /courses/{course}/assignments/{assignment}/submissions
POST    /courses/{course}/assignments/{assignment}/mark
GET     /courses/{course}/assignments/{assignment}/submissions/{submission}/marking

# Course Files
GET     /courses/{course}/files
POST    /courses/{course}/files/folders
PUT     /courses/{course}/files/folders/{folder}
DELETE  /courses/{course}/files/folders/{folder}
POST    /courses/{course}/files/upload
GET     /courses/{course}/files/compliance

# Google Drive
GET     /settings/drive/connect
GET     /settings/drive/callback
DELETE  /settings/drive/disconnect

# Admin
GET     /admin/settings
GET     /admin/faculties
GET     /admin/programmes
GET     /admin/checklists
GET     /admin/users
```

### 10.2 API Routes (Sanctum — Student PWA + AJAX)

```
# Student APIs
POST    /api/attendance/check-in          # QR scan check-in
GET     /api/quiz-sessions/{code}/join    # join live quiz
POST    /api/quiz-sessions/{code}/respond # submit answer
GET     /api/quiz-sessions/{code}/status  # poll quiz state
POST    /api/assignments/{id}/submit      # submit assignment
GET     /api/marks                        # student's own marks
GET     /api/feedback/{id}                # view feedback
GET     /api/notifications                # notification list
PUT     /api/notifications/{id}/read      # mark as read
POST    /api/sections/enroll              # self-enroll with code

# Lecturer AJAX APIs (used by Livewire/Alpine)
GET     /api/attendance-sessions/{id}/live    # real-time attendance count
GET     /api/quiz-sessions/{id}/results       # real-time quiz results
POST    /api/ai/teaching-plan/generate        # trigger AI generation
POST    /api/ai/marking/process               # trigger AI marking
GET     /api/ai/marking/{submission}/status    # poll marking progress
```

---

## 11. PWA Architecture

### 11.1 Service Worker Strategy

```
Strategy: Network-first for API, Cache-first for static assets

Cached:
  - CSS, JS bundles
  - App shell HTML
  - Icons and images
  - Translations (JSON)

Not cached:
  - API responses (always fresh)
  - File uploads/downloads
```

### 11.2 Web App Manifest

```json
{
    "name": "Lectura - Teaching Management",
    "short_name": "Lectura",
    "start_url": "/dashboard",
    "display": "standalone",
    "theme_color": "#1e40af",
    "background_color": "#ffffff",
    "icons": [
        {"src": "/icons/192.png", "sizes": "192x192", "type": "image/png"},
        {"src": "/icons/512.png", "sizes": "512x512", "type": "image/png"}
    ]
}
```

### 11.3 Student vs Lecturer PWA Views

Both use the same PWA but different navigation and layouts:

- **Student**: Bottom tab navigation (mobile-optimised), minimal chrome
- **Lecturer**: Sidebar navigation (desktop-optimised), full feature access

Role detection at login determines layout.

---

## 12. Internationalisation

### 12.1 Translation Structure

```
lang/
├── en/
│   ├── auth.php
│   ├── courses.php
│   ├── attendance.php
│   ├── quizzes.php
│   ├── assignments.php
│   ├── files.php
│   ├── dashboard.php
│   └── notifications.php
├── ms/                        # Bahasa Melayu
│   ├── auth.php
│   ├── courses.php
│   └── ...
├── en.json                    # frontend strings
└── ms.json
```

### 12.2 Locale Resolution Order

1. User preference (`users.locale`)
2. Tenant default (`tenants.locale`)
3. System default (`en`)

---

## 13. Key Design Decisions

### 13.1 Why Single Database Multi-Tenancy

- MVP scale (20 tenants) doesn't justify multi-DB complexity
- Simpler deployment, backup, and migration
- Global scope queries (cross-tenant reporting) are straightforward
- Can migrate to database-per-tenant later if needed by adding a database resolver

### 13.2 Why Blade + Livewire Instead of SPA

- Faster MVP development (no separate frontend build)
- Better SEO for public pages
- Livewire handles real-time UI (quiz, attendance) without React/Vue complexity
- Tailwind + Alpine.js for lightweight interactivity
- PWA still achievable with service worker on top of server-rendered pages

### 13.3 Why Queue-Based AI Processing

- AI calls take 10–60 seconds — too slow for synchronous requests
- Queue enables retry, rate limiting, and priority management
- Lecturer gets immediate feedback ("Processing...") with notification on completion
- Multiple lecturers can submit marking simultaneously without blocking

### 13.4 Why Google Drive Over Local Storage

- Offloads file storage cost to lecturer/institution
- Files accessible outside Lectura (portability)
- No large file storage infrastructure needed for MVP
- Metadata-only in DB keeps database lean

### 13.5 Why HMAC-Based QR Rotation

- No server round-trip needed for each QR refresh
- Lecturer's browser generates tokens using shared secret
- Server validates by recomputing HMAC within time window
- Reduces server load during large attendance sessions

---

## Entity Relationship Summary

```
Tenant ──┬── Faculty ──── Programme
         ├── AcademicTerm
         ├── TenantUser ──── User (global)
         ├── Course ──┬── Section ──── SectionStudent
         │            ├── CourseLearningOutcome
         │            ├── CourseTopic
         │            ├── TeachingPlan ──── TeachingPlanWeek ──── Activity
         │            ├── Assignment ──┬── Rubric ──── RubricCriteria ──── RubricLevel
         │            │                ├── AssignmentGroup ──── AssignmentGroupMember
         │            │                └── Submission ──┬── SubmissionFile
         │            │                                 ├── MarkingSuggestion
         │            │                                 └── Feedback
         │            ├── StudentMark
         │            ├── CourseFolder ──── CourseFile ──── FileTag
         │            └── QuizSession ──┬── QuizSessionQuestion
         │                              └── QuizParticipant ──── QuizResponse
         ├── AttendanceSession ──── AttendanceRecord
         ├── Question ──── QuestionOption
         ├── FolderTemplate
         ├── ComplianceChecklist ──── ComplianceChecklistItem
         ├── DriveConnection
         ├── AiUsageLog
         └── AuditLog

Notifications ──── User (polymorphic)
```

---

*End of Architecture & Database Design Document*

**Total tables: 34**
