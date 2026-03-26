# Implementation Workflow — Lectura

**AI-Powered Teaching Management PWA**

Version: 1.0
Date: 2026-03-26
Target: 12-week MVP (single developer)
Companion to: SRS-Lectura.md, Architecture-Lectura.md

---

## Table of Contents

1. [Implementation Strategy](#1-implementation-strategy)
2. [Sprint Overview](#2-sprint-overview)
3. [Phase 0 — Project Scaffolding](#3-phase-0--project-scaffolding-week-1)
4. [Phase 1 — Auth & Multi-Tenancy](#4-phase-1--auth--multi-tenancy-week-12)
5. [Phase 2 — Course & Section Management](#5-phase-2--course--section-management-week-23)
6. [Phase 3 — Teaching Plan Builder](#6-phase-3--teaching-plan-builder-week-34)
7. [Phase 4 — QR Attendance](#7-phase-4--qr-attendance-week-45)
8. [Phase 5 — Live Quiz System](#8-phase-5--live-quiz-system-week-56)
9. [Phase 6 — Assignment & Submission](#9-phase-6--assignment--submission-week-67)
10. [Phase 7 — AI Marking & Feedback](#10-phase-7--ai-marking--feedback-week-78)
11. [Phase 8 — Course File Management](#11-phase-8--course-file-management-week-89)
12. [Phase 9 — Google Drive Integration](#12-phase-9--google-drive-integration-week-910)
13. [Phase 10 — Dashboard & Notifications](#13-phase-10--dashboard--notifications-week-1011)
14. [Phase 11 — PWA & Polish](#14-phase-11--pwa--polish-week-1112)
15. [Phase 12 — Testing & Launch Prep](#15-phase-12--testing--launch-prep-week-12)
16. [Dependency Graph](#16-dependency-graph)
17. [Risk Register](#17-risk-register)
18. [Quality Gates](#18-quality-gates)

---

## 1. Implementation Strategy

### 1.1 Approach

**Vertical slices** — each phase delivers a working, testable feature end-to-end (migration → model → service → controller → views → tests). This allows early feedback from pilot users and avoids a "big bang" integration at the end.

### 1.2 Guiding Principles

- **Foundation first**: Auth, tenancy, and course management must be solid before anything else
- **AI as enhancement**: Core flows (attendance, quiz, assignment) must work without AI; AI layers are added after the base works
- **Real-time late**: WebSocket features (live quiz) come after CRUD is stable
- **Drive last**: Google Drive integration is additive — the app must work with local/temp storage first
- **Test as you go**: Write feature tests per phase, not at the end

### 1.3 Weekly Cadence

Each week:
- Mon–Thu: Implementation
- Fri: Testing, documentation, and review of what was built
- End of week: Demo-able state — each phase should produce something you can show

---

## 2. Sprint Overview

```
Week  1  ░░░░░░░░░░░░ Phase 0: Scaffolding + Phase 1: Auth & Tenancy
Week  2  ░░░░░░░░░░░░ Phase 1: Auth (cont.) + Phase 2: Course & Section
Week  3  ░░░░░░░░░░░░ Phase 2: Course (cont.) + Phase 3: Teaching Plan
Week  4  ░░░░░░░░░░░░ Phase 3: Teaching Plan (cont.) + Phase 4: QR Attendance
Week  5  ░░░░░░░░░░░░ Phase 4: Attendance (cont.) + Phase 5: Live Quiz
Week  6  ░░░░░░░░░░░░ Phase 5: Live Quiz (cont.)
Week  7  ░░░░░░░░░░░░ Phase 6: Assignment & Submission
Week  8  ░░░░░░░░░░░░ Phase 7: AI Marking & Feedback
Week  9  ░░░░░░░░░░░░ Phase 8: Course File Management
Week 10  ░░░░░░░░░░░░ Phase 9: Google Drive Integration
Week 11  ░░░░░░░░░░░░ Phase 10: Dashboard & Notifications + Phase 11: PWA
Week 12  ░░░░░░░░░░░░ Phase 12: Testing, Polish & Launch Prep
```

---

## 3. Phase 0 — Project Scaffolding (Week 1)

**Goal**: Fresh Laravel 12 project with all tooling configured and ready for development.

### Tasks

#### 0.1 Laravel Project Setup
- [ ] Install Laravel 12 via Composer
- [ ] Configure `.env` for local development (MySQL, Redis, Mail)
- [ ] Set up Git repository with `.gitignore`
- [ ] Create initial `CLAUDE.md` with project conventions

#### 0.2 Package Installation
- [ ] `laravel/breeze` — authentication scaffolding (Blade stack)
- [ ] `laravel/sanctum` — API token authentication
- [ ] `laravel/socialite` — Google OAuth
- [ ] `laravel/reverb` — WebSocket server
- [ ] `livewire/livewire` — reactive UI components
- [ ] `maatwebsite/excel` — Excel export
- [ ] `barryvdh/laravel-dompdf` — PDF generation
- [ ] `simplesoftwareio/simple-qrcode` — QR code generation
- [ ] `spatie/laravel-activitylog` — audit logging
- [ ] Configure Tailwind CSS + Alpine.js (included with Breeze)

#### 0.3 Configuration
- [ ] Configure Redis for cache, session, and queue
- [ ] Configure queue connections: `default`, `ai-processing`, `drive-sync`, `exports`
- [ ] Set up Laravel Reverb configuration
- [ ] Configure mail (Mailtrap for dev, Mailgun/SES for prod)
- [ ] Create `config/lectura.php` for app-specific settings

#### 0.4 Base Architecture
- [ ] Create `BelongsToTenant` trait with global scope
- [ ] Create `HasTags` trait for file tagging
- [ ] Create base `TenantController` with tenant resolution
- [ ] Create `ResolveTenant` middleware
- [ ] Create `EnsureTenantAccess` middleware
- [ ] Create `SetLocale` middleware
- [ ] Set up translation files structure (`lang/en/`, `lang/ms/`)
- [ ] Create base Blade layout with Tailwind (lecturer sidebar + student bottom nav)

#### 0.5 Database Foundation
- [ ] Create migration: `tenants` table
- [ ] Create migration: `users` table (extend default)
- [ ] Create migration: `tenant_users` pivot table
- [ ] Create `Tenant` model
- [ ] Create `TenantUser` model
- [ ] Create `TenantSeeder` with test institution
- [ ] Create `UserSeeder` with test super admin, lecturer, student
- [ ] Run migrations and verify

### Checkpoint 0
- [ ] `php artisan serve` shows Lectura login page
- [ ] Redis connected (cache, session working)
- [ ] Migrations run cleanly
- [ ] Tenant resolution middleware working
- [ ] Base layout renders with sidebar/bottom nav based on role
- [ ] Translations switch between English and Bahasa Melayu

**Deliverable**: Bootable Laravel app with auth scaffolding, tenancy foundation, and base layouts.

---

## 4. Phase 1 — Auth & Multi-Tenancy (Week 1–2)

**Goal**: Working multi-tenant authentication with role-based access.

**Depends on**: Phase 0

### Tasks

#### 1.1 Authentication
- [ ] Customize Breeze registration: add name, select institution (if applicable)
- [ ] Implement email/password login with tenant context
- [ ] Add Google OAuth via Socialite (login + registration)
- [ ] Implement "remember me" functionality
- [ ] Create email verification flow
- [ ] Add password reset flow

#### 1.2 Multi-Tenant User Management
- [ ] Implement user-tenant association (one user, multiple tenants)
- [ ] Create tenant switcher UI (for users in multiple tenants)
- [ ] Implement role checking: `$user->hasRoleInTenant($tenantId, ['lecturer'])`
- [ ] Create `TenantUserPolicy` for managing memberships
- [ ] Implement tenant-scoped session (store current tenant in session)

#### 1.3 Super Admin Panel
- [ ] Create super admin layout (separate from tenant layout)
- [ ] CRUD for tenants: create, edit, activate/deactivate
- [ ] Tenant settings form (JSON editor for auth, AI, storage, privacy settings)
- [ ] View/manage tenant users
- [ ] Seed: create default super admin account

#### 1.4 Tenant Admin Panel
- [ ] Create tenant admin layout (within tenant context)
- [ ] Manage users within tenant (invite, assign roles)
- [ ] View tenant settings (read-only for most, editable for admin-level settings)
- [ ] Institution profile page (name, logo, branding)

#### 1.5 Language Switching
- [ ] Implement locale switcher in UI (dropdown in nav)
- [ ] Store user locale preference in `users.locale`
- [ ] Apply locale resolution: user > tenant > system default
- [ ] Translate auth pages (login, register, forgot password) to BM

### Checkpoint 1
- [ ] Lecturer can register, log in, and see tenant dashboard
- [ ] Student can register, log in, and see student dashboard
- [ ] Google login creates/links account
- [ ] Super admin can create tenants
- [ ] Tenant admin can invite users
- [ ] User in multiple tenants can switch between them
- [ ] Language switches between EN and BM
- [ ] All routes enforce tenant isolation (user A cannot see tenant B data)

**Deliverable**: Fully working multi-tenant auth system with role-based routing.

---

## 5. Phase 2 — Course & Section Management (Week 2–3)

**Goal**: Lecturers can create courses, sections, manage CLOs, and enroll students.

**Depends on**: Phase 1

### Tasks

#### 2.1 Academic Structure (optional layers)
- [ ] Migration + Model: `faculties`
- [ ] Migration + Model: `programmes`
- [ ] Migration + Model: `academic_terms`
- [ ] Admin UI: manage faculties, programmes, academic terms
- [ ] Seed default academic terms for test tenant

#### 2.2 Course Management
- [ ] Migration + Model: `courses`
- [ ] `CourseController` with CRUD actions
- [ ] Course creation form: code, title, semester, teaching mode, format, num_weeks
- [ ] Course list view (filterable by term, status)
- [ ] Course detail/overview page
- [ ] `CoursePolicy` — only owning lecturer + admin can manage
- [ ] Course status workflow: draft → active → archived

#### 2.3 Course Learning Outcomes
- [ ] Migration + Model: `course_learning_outcomes`
- [ ] CLO entry form within course (add/edit/delete/reorder)
- [ ] Drag-and-drop reordering (Alpine.js + Sortable.js)

#### 2.4 Course Topics
- [ ] Migration + Model: `course_topics`
- [ ] Topic entry by week within course
- [ ] CLO mapping per topic (multi-select from course CLOs)

#### 2.5 Section Management
- [ ] Migration + Model: `sections`
- [ ] Section CRUD within course
- [ ] Auto-generate invite code on section creation
- [ ] Section schedule entry (JSON: day, time, room)
- [ ] Section list view within course

#### 2.6 Student Enrollment
- [ ] Migration + Model: `section_students`
- [ ] Manual student add form (name, email, student ID)
- [ ] CSV import: parse name, email, student_id columns
  - [ ] `ImportStudentCsv` job for async processing
  - [ ] Progress feedback and error reporting
  - [ ] Handle: auto-create student user if email doesn't exist
- [ ] Student self-enrollment: enter invite code → join section
- [ ] Student roster view with enrollment method column
- [ ] Remove/deactivate student from section

### Checkpoint 2
- [ ] Lecturer creates course with code, title, CLOs, topics
- [ ] Lecturer creates sections with schedule and invite codes
- [ ] CSV import works (10+ students, error handling)
- [ ] Student self-enrolls via invite code
- [ ] Course list filters by semester/status
- [ ] Tenant data isolation verified (lecturer in Tenant A cannot see Tenant B courses)

**Deliverable**: Complete course and section management with student enrollment.

---

## 6. Phase 3 — Teaching Plan Builder (Week 3–4)

**Goal**: Lecturers can generate AI-powered weekly teaching plans and export to PDF.

**Depends on**: Phase 2 (courses and topics must exist)

### Tasks

#### 3.1 AI Service Foundation
- [ ] Create `AiProviderInterface` contract
- [ ] Implement `ClaudeProvider` (primary)
- [ ] Implement `OpenAiProvider` (secondary)
- [ ] Create `AiServiceManager` — resolves provider per tenant config
- [ ] Create `config/ai.php` with provider configurations
- [ ] Implement AI quota checking middleware
- [ ] Migration + Model: `ai_usage_logs`
- [ ] AI usage logging on every request

#### 3.2 Teaching Plan Data Layer
- [ ] Migration + Model: `teaching_plans`
- [ ] Migration + Model: `teaching_plan_weeks`
- [ ] Migration + Model: `activities`
- [ ] Relationships: Course → TeachingPlans → TeachingPlanWeeks → Activities

#### 3.3 Plan Generation
- [ ] Create `TeachingPlannerService`
- [ ] Create prompt template: `resources/views/ai/prompts/teaching-plan/generate-week.blade.php`
- [ ] Create `GenerateTeachingPlan` job (queued on `ai-processing`)
- [ ] Generate plan for all weeks in one batch OR per-week
- [ ] Parse AI response into structured `teaching_plan_weeks` records
- [ ] Store active learning suggestions in `activities` table

#### 3.4 Plan UI
- [ ] Teaching plan overview page (all weeks, expandable)
- [ ] Plan generation trigger button with loading state
- [ ] Inline editing of plan content (week by week)
- [ ] Accept/regenerate controls per week
- [ ] Activity suggestions display within each week
- [ ] Livewire component for real-time status updates during generation

#### 3.5 Plan Versioning
- [ ] Implement version increment on save
- [ ] Version history list (version number, timestamp, editor, change note)
- [ ] View previous version in read-only mode
- [ ] "Publish" action to mark version as current

#### 3.6 PDF Export
- [ ] Create PDF template for teaching plan
- [ ] Include: course info header, weekly breakdown, CLO mapping, activities
- [ ] `ExportTeachingPlanPdf` job
- [ ] Download endpoint returning PDF

#### 3.7 Translations
- [ ] Translate teaching plan UI strings to BM
- [ ] AI prompt should respect course language setting (generate plan in EN or BM)

### Checkpoint 3
- [ ] Lecturer clicks "Generate Plan" → AI produces 14-week plan
- [ ] Plan displays with weekly breakdown, activities, time allocation
- [ ] Lecturer edits week 5 content and saves
- [ ] Version history shows v1 and v2
- [ ] PDF export downloads with professional formatting
- [ ] AI usage logged with token count
- [ ] Queue worker processes generation within 2 minutes

**Deliverable**: AI-powered teaching plan generation with editing, versioning, and PDF export.

---

## 7. Phase 4 — QR Attendance (Week 4–5)

**Goal**: Lecturers generate rotating QR codes; students scan to check in.

**Depends on**: Phase 2 (sections and students must exist)

### Tasks

#### 4.1 Attendance Data Layer
- [ ] Migration + Model: `attendance_sessions`
- [ ] Migration + Model: `attendance_records`
- [ ] Relationships: Section → AttendanceSessions → AttendanceRecords

#### 4.2 QR Code System
- [ ] Create `QrCodeService`
- [ ] Implement HMAC-based token generation: `HMAC(session_secret, floor(timestamp / 30))`
- [ ] QR encodes: `{session_id}:{token}`
- [ ] Server-side validation: accept current token OR previous rotation (60s grace)
- [ ] Generate QR as SVG using BaconQrCode

#### 4.3 Lecturer Attendance Flow
- [ ] "Start Session" button on section page
- [ ] Session type selector (lecture/tutorial/lab/extra)
- [ ] QR display page (full-screen option for projector)
- [ ] QR auto-rotates via JavaScript timer (30s interval, regenerates HMAC client-side)
- [ ] Live check-in counter (Livewire polling or Reverb broadcast)
- [ ] "End Session" button
- [ ] Manual override form: mark student present/absent/late/excused with reason

#### 4.4 Student Check-In Flow
- [ ] QR scanner page using `html5-qrcode` library
- [ ] Camera permission request and fallback instructions
- [ ] Scan → POST to `/api/attendance/check-in`
- [ ] API validates: token valid, session active, student enrolled, not duplicate
- [ ] Success/error feedback UI
- [ ] Late flag if checked in after `late_threshold_minutes`

#### 4.5 Attendance Reports
- [ ] Attendance history view per section (table: date, session type, present/late/absent counts)
- [ ] Per-student attendance summary (percentage, session list)
- [ ] Repeated absence alert: flag students with 3+ consecutive absences
- [ ] Excel export: `ExportAttendanceExcel` using Maatwebsite
- [ ] PDF export: `ExportAttendancePdf` using DomPDF

#### 4.6 Translations
- [ ] Translate attendance UI to BM

### Checkpoint 4
- [ ] Lecturer starts session → QR displays and rotates every 30s
- [ ] Student scans QR → check-in recorded with timestamp
- [ ] Late check-in flagged correctly
- [ ] Duplicate scan prevented
- [ ] Manual override works
- [ ] Attendance report exports to Excel and PDF
- [ ] Absence alerts shown for frequently absent students

**Deliverable**: Complete QR attendance system with reports and exports.

---

## 8. Phase 5 — Live Quiz System (Week 5–6)

**Goal**: Real-time classroom quiz with MCQ/TF/short-answer, leaderboard, and question bank.

**Depends on**: Phase 2 (sections must exist), Phase 0 (Reverb configured)

### Tasks

#### 5.1 Quiz Data Layer
- [ ] Migration + Model: `quiz_sessions`
- [ ] Migration + Model: `questions`
- [ ] Migration + Model: `question_options`
- [ ] Migration + Model: `quiz_session_questions`
- [ ] Migration + Model: `quiz_participants`
- [ ] Migration + Model: `quiz_responses`

#### 5.2 Question Bank
- [ ] Question CRUD: create MCQ, true/false, short answer
- [ ] MCQ: question text + 2–6 options + correct answer(s)
- [ ] True/false: question text + correct answer
- [ ] Short answer: question text + accepted answers (comma-separated)
- [ ] Tag questions (topic, CLO, difficulty)
- [ ] Question bank list with search and filter
- [ ] Import questions into quiz session from bank

#### 5.3 Quiz Session Management
- [ ] Create quiz session: title, mode (formative/participation/graded), anonymous flag
- [ ] Auto-generate join code (6–8 chars)
- [ ] Add questions to session (from bank or create ad hoc)
- [ ] Reorder questions with drag-and-drop
- [ ] Set time limit per question

#### 5.4 Real-Time Quiz Engine
- [ ] Configure Reverb channels: `tenant.{id}.quiz.{sessionId}`
- [ ] Lecturer "Start Quiz" → broadcast session active
- [ ] Lecturer "Next Question" → broadcast question to all participants
- [ ] Lecturer "Close Question" → broadcast answers closed
- [ ] Student receives question → displays with countdown timer
- [ ] Student submits answer → `QuizResponseReceived` event broadcast
- [ ] Create Livewire components:
  - [ ] `LecturerQuizDashboard`: real-time answer distribution chart, participation count
  - [ ] `StudentQuizPlayer`: question display, answer submission, waiting screen
  - [ ] `QuizLeaderboard`: live score ranking (hidden in anonymous mode)

#### 5.5 Scoring & Results
- [ ] Auto-score MCQ and true/false immediately
- [ ] Short answer: exact match or contains match (configurable)
- [ ] Calculate points per question (speed bonus optional)
- [ ] Update `quiz_participants.total_score` after each question
- [ ] Session results summary: per-question accuracy, overall stats
- [ ] Transfer graded quiz scores to `student_marks` table (for gradebook)

#### 5.6 Anonymous Mode
- [ ] Generate random display names for participants when `is_anonymous = true`
- [ ] Leaderboard shows aliases, not real names
- [ ] Lecturer can still see real identities in admin view

#### 5.7 Translations
- [ ] Translate quiz UI to BM

### Checkpoint 5
- [ ] Lecturer creates quiz with 5 MCQ questions
- [ ] 3 students join via code on their phones
- [ ] Questions appear one at a time with countdown
- [ ] Answer distribution updates in real-time on lecturer screen
- [ ] Leaderboard updates after each question
- [ ] Anonymous mode hides names
- [ ] Graded mode transfers scores to marks
- [ ] Questions saved to bank for reuse

**Deliverable**: Real-time live quiz system with question bank and multiple modes.

---

## 9. Phase 6 — Assignment & Submission (Week 6–7)

**Goal**: Lecturers create assignments with rubrics; students submit work.

**Depends on**: Phase 2 (courses, sections, students)

### Tasks

#### 6.1 Assignment Data Layer
- [ ] Migration + Model: `assignments`
- [ ] Migration + Model: `rubrics`
- [ ] Migration + Model: `rubric_criteria`
- [ ] Migration + Model: `rubric_levels`
- [ ] Migration + Model: `assignment_groups`
- [ ] Migration + Model: `assignment_group_members`
- [ ] Migration + Model: `submissions`
- [ ] Migration + Model: `submission_files`
- [ ] Migration + Model: `student_marks`

#### 6.2 Assignment Management
- [ ] Assignment CRUD: title, description, deadline, type (individual/group), total marks
- [ ] Assignment list view per course
- [ ] Status workflow: draft → published → closed → graded
- [ ] Late deadline configuration (grace period)
- [ ] Resubmission rules (allow/deny, max count)
- [ ] CLO mapping per assignment

#### 6.3 Rubric Builder
- [ ] Structured matrix rubric builder (Livewire component):
  - [ ] Add/remove criteria rows
  - [ ] Add/remove performance level columns
  - [ ] Enter marks per cell
  - [ ] Drag-and-drop reorder criteria
- [ ] Free-text rubric: criteria + max marks (simpler form)
- [ ] Preview rubric as student would see it

#### 6.4 Group Assignment
- [ ] Group creation: manual or random assignment
- [ ] Students see their group on assignment page
- [ ] One submission slot per group (any member can submit)

#### 6.5 Student Submission Flow
- [ ] Assignment detail page (student view): description, rubric, deadline countdown
- [ ] File upload: PDF (25 MB max), images (auto-compress), typed text answer
- [ ] Upload progress indicator
- [ ] Submission confirmation with timestamp
- [ ] Late submission flag if past deadline
- [ ] Resubmission: show previous submission, allow new upload if rules permit
- [ ] File temporarily stored locally (Drive sync comes in Phase 9)

#### 6.6 Lecturer Submission View
- [ ] Submission list per assignment: student name, submitted at, status, late flag
- [ ] View individual submission: files, student notes
- [ ] Manual marking form: enter marks per criteria, total auto-calculated
- [ ] Finalize marks → `student_marks` record created

#### 6.7 Translations
- [ ] Translate assignment and submission UI to BM

### Checkpoint 6
- [ ] Lecturer creates assignment with matrix rubric (4 criteria x 4 levels)
- [ ] Student uploads PDF submission before deadline
- [ ] Late submission correctly flagged
- [ ] Group assignment: one member submits, all members see it
- [ ] Lecturer views submission list, opens a submission, enters marks
- [ ] Marks saved and visible to student
- [ ] Resubmission works within configured limits

**Deliverable**: Complete assignment lifecycle from creation to manual marking.

---

## 10. Phase 7 — AI Marking & Feedback (Week 7–8)

**Goal**: AI reads typed PDF submissions, suggests marks, and generates feedback.

**Depends on**: Phase 3 (AI service layer), Phase 6 (assignments and submissions)

### Tasks

#### 7.1 Marking Assistant Service
- [ ] Create `MarkingAssistantService`
- [ ] Create prompt template: `extract-answers.blade.php` — extract student answers from PDF text
- [ ] Create prompt template: `suggest-marks.blade.php` — compare answers to scheme, suggest marks
- [ ] Handle multi-question submissions (extract per question)
- [ ] Return structured response: `[{question, extracted_answer, suggested_marks, explanation, confidence}]`

#### 7.2 AI Marking Data Layer
- [ ] Migration + Model: `marking_suggestions`
- [ ] Migration + Model: `feedbacks`

#### 7.3 AI Marking Flow
- [ ] Lecturer clicks "AI Mark" on a submission
- [ ] System extracts text from PDF (using PHP PDF parser or AI vision)
- [ ] `ProcessAiMarking` job dispatched to `ai-processing` queue
- [ ] AI processes: extract answers → compare to scheme → suggest marks
- [ ] Results saved to `marking_suggestions` table
- [ ] Submission status: `submitted` → `ai_processing` → `ai_completed`
- [ ] Lecturer notified when AI marking complete

#### 7.4 Marking Review UI
- [ ] Per-student marking review page (Livewire component):
  - [ ] Left panel: student's submission (PDF viewer or extracted text)
  - [ ] Right panel: AI suggestions per question/criteria
  - [ ] Per-suggestion: extracted answer, suggested marks, explanation, confidence bar
  - [ ] Accept / Modify / Reject buttons per suggestion
  - [ ] Modify: inline edit of marks with lecturer note
- [ ] "Accept All" bulk action (for high-confidence suggestions)
- [ ] "Finalize Marks" → transfers confirmed marks to `student_marks`

#### 7.5 Feedback Generation
- [ ] Create `FeedbackGeneratorService`
- [ ] Create prompt template: `feedback/generate.blade.php`
- [ ] Input: student marks, rubric, performance level, submission content
- [ ] Output: strengths, missing points, misconceptions, revision advice, improvement tips
- [ ] `GenerateFeedback` job dispatched after marks finalized
- [ ] Feedback saved to `feedbacks` table
- [ ] Lecturer can edit AI-generated feedback before releasing
- [ ] "Release Feedback" button → sets `is_released = true`

#### 7.6 Student Feedback View
- [ ] Student views assignment → sees marks summary
- [ ] Feedback section: strengths, areas to improve, revision advice
- [ ] Notification sent when feedback released

#### 7.7 Answer Scheme Upload
- [ ] Allow lecturer to upload answer scheme as file or enter as text
- [ ] AI uses scheme as reference for marking comparison

#### 7.8 Translations
- [ ] Translate marking and feedback UI to BM
- [ ] AI feedback should be generated in the course's language setting

### Checkpoint 7
- [ ] Lecturer uploads answer scheme for assignment
- [ ] Lecturer clicks "AI Mark" on a typed PDF submission
- [ ] AI extracts answers, suggests marks with confidence scores
- [ ] Lecturer reviews, modifies some suggestions, accepts others
- [ ] Marks finalized and visible to student
- [ ] AI generates feedback with strengths and improvement areas
- [ ] Lecturer releases feedback → student receives notification
- [ ] AI usage logged correctly

**Deliverable**: AI-assisted marking pipeline with lecturer review and auto-feedback generation.

---

## 11. Phase 8 — Course File Management (Week 8–9)

**Goal**: Flexible folder system with templates, file tagging, and compliance tracking.

**Depends on**: Phase 2 (courses must exist)

### Tasks

#### 8.1 File Management Data Layer
- [ ] Migration + Model: `folder_templates`
- [ ] Migration + Model: `course_folders`
- [ ] Migration + Model: `course_files`
- [ ] Migration + Model: `file_tags`
- [ ] Migration + Model: `compliance_checklists`
- [ ] Migration + Model: `compliance_checklist_items`

#### 8.2 Folder Template System
- [ ] Seed global default folder template (13 default folders from SRS)
- [ ] Admin UI: manage tenant-level default template
- [ ] Optional: faculty/programme-level template override
- [ ] Lecturer: save current folder structure as personal template
- [ ] Template resolution order: personal > programme > faculty > tenant > global

#### 8.3 Course Folder Management
- [ ] Auto-create folders from resolved template when course is created
- [ ] Folder tree view (Livewire component with Alpine.js):
  - [ ] Expandable/collapsible tree
  - [ ] Rename folder (inline edit)
  - [ ] Add new folder / subfolder
  - [ ] Delete folder (confirm if contains files)
  - [ ] Drag-and-drop reorder
  - [ ] Merge folders (move files, delete empty)

#### 8.4 File Upload & Management
- [ ] File upload within folder context
- [ ] Upload validation: file type whitelist, size limit (25 MB)
- [ ] Image compression on upload (using Intervention Image or similar)
- [ ] File list within folder: name, type, size, uploader, date
- [ ] File preview (PDF viewer inline, image viewer)
- [ ] File delete with confirmation
- [ ] File move between folders

#### 8.5 File Tagging
- [ ] Tag assignment UI: multi-select tags per file
- [ ] Tag types: week, CLO, assessment type, section, topic, evidence type, semester, activity type
- [ ] Tag autocomplete from existing values
- [ ] Filter files by tags (cross-folder view)
- [ ] Search files by name and tags

#### 8.6 Compliance Checklist
- [ ] Admin UI: create/edit compliance checklist per tenant or faculty
- [ ] Checklist items: title, rule type (file_exists, tag_exists, folder_not_empty, custom)
- [ ] Rule config JSON for automated checking
- [ ] `ComplianceService`: evaluate course against checklist
- [ ] Compliance dashboard widget: completion percentage, missing items list
- [ ] Per-course compliance detail view

#### 8.7 Translations
- [ ] Translate file management UI to BM

### Checkpoint 8
- [ ] New course auto-creates default folder structure
- [ ] Lecturer renames "Attendance Records" to "Rekod Kehadiran"
- [ ] Lecturer adds subfolder "Week 1-7" under "Weekly Materials"
- [ ] File uploaded, tagged with "Week 3" + "CLO1"
- [ ] Filter shows all files tagged "CLO1" across folders
- [ ] Compliance dashboard shows 60% complete with 5 missing items
- [ ] Lecturer saves their structure as personal template

**Deliverable**: Flexible course file management with templates, tagging, and compliance tracking.

---

## 12. Phase 9 — Google Drive Integration (Week 9–10)

**Goal**: Files sync to lecturer's Google Drive; folder structure mirrored.

**Depends on**: Phase 8 (course files must exist)

### Tasks

#### 9.1 Drive Connection
- [ ] Migration + Model: `drive_connections`
- [ ] Create `GoogleDriveService`
- [ ] Google Cloud Console: configure OAuth consent screen, Drive API
- [ ] OAuth flow: lecturer connects Drive → tokens stored (encrypted)
- [ ] Token refresh logic (scheduled job to refresh before expiry)
- [ ] Disconnect flow with warning dialog
- [ ] Connection status indicator in settings

#### 9.2 Drive Folder Sync
- [ ] `CreateDriveFolders` job: mirror `course_folders` tree to Drive
- [ ] Create root folder: `Lectura/{course_code} - {title} ({term})/`
- [ ] Store `drive_folder_id` on each `course_folders` record
- [ ] When lecturer adds/renames/deletes folder in app → sync to Drive
- [ ] Handle Drive API errors gracefully (retry, log, notify)

#### 9.3 File Upload to Drive
- [ ] `SyncFileToDrive` job: upload file from temp storage to correct Drive folder
- [ ] Update `course_files` with `drive_file_id` and `drive_url`
- [ ] Delete local temp file after successful sync
- [ ] Update `submission_files` similarly for student submissions
- [ ] File status tracking: uploading → synced / failed
- [ ] Retry failed uploads (button in UI + automatic retry via queue)

#### 9.4 Drive Disconnection Handling
- [ ] Mark all files as "unavailable" when Drive disconnected
- [ ] Metadata preserved in database
- [ ] UI shows "Drive disconnected" warning on file pages
- [ ] Reconnection: re-link existing Drive folders if found

#### 9.5 Settings UI
- [ ] Drive connection page in lecturer settings
- [ ] Connection status, email, storage usage
- [ ] Connect / Disconnect buttons
- [ ] "Sync All" manual trigger
- [ ] Sync status log (last sync, errors)

#### 9.6 Translations
- [ ] Translate Drive settings UI to BM

### Checkpoint 9
- [ ] Lecturer connects Google Drive via OAuth
- [ ] Course folders automatically created on Drive
- [ ] File uploaded in app appears on Drive within 30 seconds
- [ ] Folder rename in app reflected on Drive
- [ ] Disconnect shows warning → files marked unavailable
- [ ] Reconnect re-links folders
- [ ] Student submission files sync to Drive

**Deliverable**: Google Drive integration with automatic folder/file sync.

---

## 13. Phase 10 — Dashboard & Notifications (Week 10–11)

**Goal**: Lecturer and student dashboards with notification system.

**Depends on**: All previous phases (aggregates data from all modules)

### Tasks

#### 10.1 Lecturer Dashboard
- [ ] Today's classes (from section schedules matching current day)
- [ ] Active courses list with status indicators
- [ ] Attendance status: sessions today, check-in rates
- [ ] Pending assessments: assignments awaiting marking
- [ ] AI marking queue: submissions in `ai_processing` status
- [ ] Weekly teaching progress: current week vs planned content
- [ ] Course file completeness: per-course compliance percentage
- [ ] Quick action buttons: start attendance, create quiz, upload file

#### 10.2 Student Dashboard
- [ ] My courses and sections
- [ ] Upcoming deadlines (assignments due)
- [ ] Recent marks and feedback
- [ ] Attendance summary (percentage per course)
- [ ] Active live sessions (quiz or attendance currently running)
- [ ] Notifications feed

#### 10.3 Notification System
- [ ] Use Laravel's built-in notification system
- [ ] Create notification classes:
  - [ ] `AssignmentPublished` → student
  - [ ] `DeadlineReminder` → student (scheduled: 24h and 1h before)
  - [ ] `FeedbackReleased` → student
  - [ ] `QuizStarted` → student
  - [ ] `AttendanceSessionOpen` → student
  - [ ] `SubmissionReceived` → lecturer
  - [ ] `MarkingReady` → lecturer
  - [ ] `ComplianceMissing` → lecturer
- [ ] In-app notification: database channel
- [ ] Email notification: mail channel (configurable per notification type)
- [ ] Notification bell icon with unread count (Livewire polling)
- [ ] Notification dropdown/page with mark-as-read
- [ ] Student notification preferences (optional: toggle email per type)

#### 10.4 Scheduled Tasks
- [ ] Deadline reminder: `schedule->daily()` — find assignments due in 24h and 1h
- [ ] Absence alert: `schedule->daily()` — flag students with 3+ consecutive absences
- [ ] Drive token refresh: `schedule->hourly()` — refresh expiring OAuth tokens
- [ ] AI quota reset: `schedule->monthly()` — reset tenant usage counters

#### 10.5 Translations
- [ ] Translate dashboard and notification strings to BM

### Checkpoint 10
- [ ] Lecturer dashboard shows today's schedule, pending marking, AI queue
- [ ] Student dashboard shows upcoming deadlines, recent marks
- [ ] Notification bell shows unread count
- [ ] Student receives email when feedback released
- [ ] Deadline reminder emails sent 24h before due date
- [ ] All dashboard cards link to relevant detail pages

**Deliverable**: Role-specific dashboards with in-app and email notifications.

---

## 14. Phase 11 — PWA & Polish (Week 11–12)

**Goal**: Progressive Web App capabilities, responsive design polish, and cross-browser testing.

**Depends on**: All previous phases

### Tasks

#### 11.1 PWA Setup
- [ ] Create `manifest.json` (app name, icons, theme color, start URL)
- [ ] Create service worker: cache static assets (CSS, JS, icons, fonts)
- [ ] Network-first strategy for API calls
- [ ] Cache-first strategy for static assets
- [ ] App shell caching for offline loading screen
- [ ] Install prompt handling (Add to Home Screen)
- [ ] Generate PWA icons (192px, 512px, maskable)

#### 11.2 Responsive Design Polish
- [ ] Audit all pages on mobile viewport (375px, 414px)
- [ ] Student views: optimize for bottom-tab mobile navigation
- [ ] Lecturer views: sidebar collapses to hamburger on mobile
- [ ] QR display: full-screen mode optimized for projector
- [ ] QR scanner: full-viewport camera on mobile
- [ ] Quiz player: touch-friendly buttons, large text
- [ ] File upload: mobile-friendly with camera capture for images
- [ ] Tables: horizontal scroll or card layout on mobile

#### 11.3 Accessibility Audit
- [ ] Keyboard navigation: all interactive elements focusable
- [ ] Tab order logical on all pages
- [ ] ARIA labels on icons, buttons, form elements
- [ ] Color contrast: verify AA compliance on all text
- [ ] Screen reader: test critical flows (login, attendance, quiz)
- [ ] Focus management on modals and dynamic content

#### 11.4 UI/UX Polish
- [ ] Loading states on all async operations (skeleton screens, spinners)
- [ ] Error states with clear messages and retry actions
- [ ] Empty states for lists (no courses yet, no submissions yet)
- [ ] Success feedback (toast notifications using Alpine.js)
- [ ] Confirm dialogs for destructive actions (delete folder, end session)
- [ ] Breadcrumb navigation on all nested pages

#### 11.5 Performance Optimization
- [ ] Eager load relationships to prevent N+1 queries
- [ ] Cache course/section data (Redis, 5-minute TTL)
- [ ] Lazy load images
- [ ] Minify CSS/JS (Vite production build)
- [ ] Database indexes verified on all queried columns
- [ ] Paginate all list views (20–50 items per page)

#### 11.6 Translation Completion
- [ ] Complete all remaining BM translations
- [ ] Verify every user-facing string has translation key
- [ ] Test full flows in BM

### Checkpoint 11
- [ ] App installable as PWA on Android and iOS
- [ ] All pages responsive on mobile
- [ ] Keyboard navigation works on all critical flows
- [ ] Color contrast passes AA check
- [ ] Page load under 3 seconds on 3G throttle
- [ ] No N+1 query warnings in debug bar
- [ ] Complete BM translation on all pages

**Deliverable**: Production-quality PWA with responsive design, accessibility, and performance optimization.

---

## 15. Phase 12 — Testing & Launch Prep (Week 12)

**Goal**: Comprehensive testing, bug fixes, and deployment preparation.

**Depends on**: All phases complete

### Tasks

#### 12.1 Automated Testing
- [ ] Feature tests for authentication flows
- [ ] Feature tests for course/section CRUD
- [ ] Feature tests for student enrollment (CSV, manual, invite)
- [ ] Feature tests for teaching plan generation and versioning
- [ ] Feature tests for attendance (start session, check-in, reports)
- [ ] Feature tests for live quiz (create, join, respond, scoring)
- [ ] Feature tests for assignment submission and marking
- [ ] Feature tests for AI marking pipeline (mock AI responses)
- [ ] Feature tests for feedback generation and release
- [ ] Feature tests for course file management (folders, upload, tags)
- [ ] Feature tests for notifications
- [ ] Feature tests for tenant isolation (critical: cross-tenant data leak prevention)
- [ ] Unit tests for QR HMAC generation and validation
- [ ] Unit tests for AI service provider resolution
- [ ] Unit tests for compliance checklist evaluation

#### 12.2 Manual Testing
- [ ] Full lecturer workflow: create course → plan → attendance → quiz → assign → mark → feedback → files
- [ ] Full student workflow: enroll → attendance → quiz → submit → view marks → view feedback
- [ ] Admin workflow: create tenant → configure → manage users → view compliance
- [ ] Multi-tenant test: verify data isolation between 2 tenants
- [ ] Test on: Chrome, Firefox, Safari (desktop + mobile)
- [ ] Test on: Android Chrome, iOS Safari
- [ ] Test with slow network (3G throttle)

#### 12.3 Security Checklist
- [ ] CSRF protection on all forms
- [ ] SQL injection: parameterized queries (Eloquent handles this)
- [ ] XSS: Blade auto-escaping verified, no `{!! !!}` on user content
- [ ] File upload: validate MIME type server-side, not just extension
- [ ] Rate limiting on login, API endpoints, AI generation
- [ ] Tenant isolation: verify global scopes active on all tenant models
- [ ] Sanctum token scoping for API routes
- [ ] Drive tokens encrypted in database
- [ ] Sensitive config in `.env`, not committed

#### 12.4 Deployment Preparation
- [ ] Production `.env` template
- [ ] Docker/Docker Compose setup (optional, depends on hosting)
- [ ] Nginx configuration
- [ ] SSL certificate (Let's Encrypt / Cloudflare)
- [ ] Database backup strategy
- [ ] Queue worker supervisor configuration (Supervisor or systemd)
- [ ] Reverb WebSocket server deployment config
- [ ] Redis production configuration
- [ ] Seed production data: super admin account, global folder template
- [ ] `php artisan optimize` / `config:cache` / `route:cache` / `view:cache`
- [ ] Health check endpoint (`/health`)
- [ ] Error monitoring (Sentry or Laravel Telescope for staging)

#### 12.5 Documentation
- [ ] Admin setup guide (tenant creation, configuration)
- [ ] Lecturer quick-start guide
- [ ] Student guide (enrollment, attendance, quiz, submission)
- [ ] API documentation (for future integrations)
- [ ] Deployment runbook

### Checkpoint 12 (Launch Readiness)
- [ ] All feature tests passing
- [ ] No critical bugs from manual testing
- [ ] Security checklist complete
- [ ] Production environment deployed and accessible
- [ ] Super admin can log in and create first tenant
- [ ] Lecturer can complete full teaching workflow
- [ ] Student can complete full participation workflow
- [ ] PWA installable on test devices
- [ ] Monitoring and backup configured

**Deliverable**: Production-ready MVP deployed and tested.

---

## 16. Dependency Graph

```
Phase 0: Scaffolding
    │
    ▼
Phase 1: Auth & Tenancy
    │
    ▼
Phase 2: Course & Section ─────────────────────────────────┐
    │                                                       │
    ├──────────────┬──────────────┬──────────────┐          │
    ▼              ▼              ▼              ▼          │
Phase 3:      Phase 4:      Phase 5:      Phase 8:         │
Teaching      QR            Live Quiz     Course Files      │
Plan          Attendance                      │             │
    │              │              │            ▼             │
    │              │              │       Phase 9:           │
    │              │              │       Drive              │
    │              │              │            │             │
    └──────────────┴──────┬───────┘            │             │
                          │                    │             │
                     Phase 6: Assignment ◄─────┘             │
                          │                                  │
                          ▼                                  │
                     Phase 7: AI Marking ◄───── Phase 3      │
                          │              (AI service layer)   │
                          │                                  │
                          └──────────┬───────────────────────┘
                                     ▼
                              Phase 10: Dashboard & Notifications
                                     │
                                     ▼
                              Phase 11: PWA & Polish
                                     │
                                     ▼
                              Phase 12: Testing & Launch
```

### Critical Path

```
Phase 0 → Phase 1 → Phase 2 → Phase 6 → Phase 7 → Phase 10 → Phase 12
```

This is the longest dependency chain. Delays in auth, courses, or assignments directly delay the entire project.

### Parallel Opportunities

After Phase 2 completes, the following can be worked on in parallel if additional developers join:

| Developer A | Developer B |
|---|---|
| Phase 3 (Teaching Plan) | Phase 4 (Attendance) |
| Phase 7 (AI Marking) | Phase 5 (Live Quiz) |
| Phase 10 (Dashboard) | Phase 8+9 (Files + Drive) |

---

## 17. Risk Register

| # | Risk | Impact | Probability | Mitigation |
|---|---|---|---|---|
| R1 | AI API rate limits or downtime during marking | High | Medium | Queue with retry, graceful degradation, manual marking fallback always available |
| R2 | Google Drive API quota exceeded | Medium | Low | Rate limit Drive sync, batch operations, warn users approaching quota |
| R3 | WebSocket scalability for live quiz | Medium | Low | Reverb handles MVP scale; monitor during pilot; fall back to polling if needed |
| R4 | PDF text extraction quality varies | High | Medium | Start with typed PDFs only; test with diverse PDF formats early; provide manual text entry fallback |
| R5 | Scope creep from pilot user feedback | High | High | Freeze MVP scope at Phase 12; track requests for Phase 2; resist adding features mid-sprint |
| R6 | Tenant isolation bug exposes cross-tenant data | Critical | Low | Automated tests for isolation; code review all queries; global scope enforcement |
| R7 | Mobile browser QR scanning inconsistencies | Medium | Medium | Test on 5+ devices early (Phase 4); provide manual code entry fallback |
| R8 | Translation coverage incomplete for BM | Low | Medium | Use translation checker tool; dedicate time in Phase 11; accept partial BM coverage at launch |
| R9 | Single developer illness/burnout | High | Medium | Vertical slices mean each phase is independently valuable; can launch with fewer phases |
| R10 | Laravel 12 or package compatibility issues | Medium | Low | Pin package versions; test early in Phase 0; have fallback to Laravel 11 |

---

## 18. Quality Gates

Each phase must pass these quality gates before moving to the next:

### Code Quality
- [ ] No PHP errors or warnings
- [ ] All new models have tenant scoping (where applicable)
- [ ] All new controllers use Form Requests for validation
- [ ] All new routes have authorization (policies or middleware)
- [ ] No raw SQL queries (use Eloquent/Query Builder)

### Testing
- [ ] Feature tests written for all new endpoints
- [ ] Tests pass on CI (or locally with `php artisan test`)
- [ ] Tenant isolation verified for any new tenant-scoped data

### UI/UX
- [ ] New pages render correctly on desktop (1280px+) and mobile (375px)
- [ ] All form fields have labels and validation error messages
- [ ] Loading states present on async operations
- [ ] New UI strings added to both `en` and `ms` translation files

### Data
- [ ] Migrations are reversible (`down()` method implemented)
- [ ] Seeders updated for new tables
- [ ] No orphan records possible (foreign keys with appropriate ON DELETE)

---

## Migration Execution Order

For reference, the complete migration order respecting foreign key dependencies:

```
001  create_tenants_table
002  update_users_table (add google_id, locale, is_super_admin, avatar_url)
003  create_tenant_users_table
004  create_faculties_table
005  create_programmes_table
006  create_academic_terms_table
007  create_courses_table
008  create_course_learning_outcomes_table
009  create_course_topics_table
010  create_sections_table
011  create_section_students_table
012  create_teaching_plans_table
013  create_teaching_plan_weeks_table
014  create_activities_table
015  create_attendance_sessions_table
016  create_attendance_records_table
017  create_quiz_sessions_table
018  create_questions_table
019  create_question_options_table
020  create_quiz_session_questions_table
021  create_quiz_participants_table
022  create_quiz_responses_table
023  create_assignments_table
024  create_rubrics_table
025  create_rubric_criteria_table
026  create_rubric_levels_table
027  create_assignment_groups_table
028  create_assignment_group_members_table
029  create_submissions_table
030  create_submission_files_table
031  create_marking_suggestions_table
032  create_student_marks_table
033  create_feedbacks_table
034  create_folder_templates_table
035  create_course_folders_table
036  create_course_files_table
037  create_file_tags_table
038  create_compliance_checklists_table
039  create_compliance_checklist_items_table
040  create_drive_connections_table
041  create_notifications_table
042  create_ai_usage_logs_table
043  create_audit_logs_table
```

---

*End of Implementation Workflow*

**Next step**: Use `/sc:implement` to begin executing Phase 0 — Project Scaffolding.
