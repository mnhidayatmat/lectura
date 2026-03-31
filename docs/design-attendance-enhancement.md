# Attendance System Enhancement - Design Specification

## Overview

Enhance the existing QR-based attendance system with: absence excuse submissions (student-initiated), configurable attendance warning thresholds (lecturer-configured per course), a dedicated student attendance page, comprehensive PDF/Excel report generation, and automatic report linking into Course Files.

---

## 1. Database Schema Changes

### 1.1 New Table: `attendance_excuses`

Student-submitted absence justifications with optional file attachment.

```sql
CREATE TABLE attendance_excuses (
    id                    BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    attendance_record_id  BIGINT UNSIGNED NOT NULL,       -- FK -> attendance_records.id (CASCADE)
    user_id               BIGINT UNSIGNED NOT NULL,       -- FK -> users.id (CASCADE)
    reason                TEXT NOT NULL,                   -- free-text explanation
    category              VARCHAR(30) NOT NULL DEFAULT 'other',
                          -- medical, family_emergency, academic_conflict, official_duty, other
    attachment_path       VARCHAR(500) NULL,               -- storage path (e.g., MC scan)
    attachment_filename   VARCHAR(255) NULL,               -- original filename
    status                VARCHAR(20) NOT NULL DEFAULT 'pending',
                          -- pending, approved, rejected
    reviewed_by           BIGINT UNSIGNED NULL,            -- FK -> users.id (SET NULL)
    reviewed_at           TIMESTAMP NULL,
    reviewer_note         VARCHAR(500) NULL,               -- optional lecturer note
    created_at            TIMESTAMP,
    updated_at            TIMESTAMP,

    UNIQUE (attendance_record_id),                         -- one excuse per record
    INDEX (user_id, status),
    INDEX (status)
);
```

**Why one excuse per record?** A student can only be absent once per session. The excuse maps 1:1 to the `attendance_records` row where `status = 'absent'`. If approved, the record status changes to `excused`.

### 1.2 New Table: `attendance_policies`

Lecturer-configured warning/threshold rules per course.

```sql
CREATE TABLE attendance_policies (
    id                      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    course_id               BIGINT UNSIGNED NOT NULL,     -- FK -> courses.id (CASCADE)
    tenant_id               BIGINT UNSIGNED NOT NULL,     -- FK -> tenants.id (CASCADE)
    mode                    VARCHAR(20) NOT NULL DEFAULT 'percentage',
                            -- 'percentage' or 'count'
    warning_thresholds      JSON NOT NULL,
                            -- e.g. [{"level":1,"value":20},{"level":2,"value":30},{"level":3,"value":40}]
    bar_threshold           DECIMAL(5,2) NULL,
                            -- final barring threshold (e.g., 50% or 7 absences)
    bar_action              VARCHAR(30) NOT NULL DEFAULT 'flag',
                            -- 'flag' (visual only), 'notify' (email), 'block' (prevent exam)
    include_late_as_absent  BOOLEAN NOT NULL DEFAULT FALSE,
                            -- count late attendance towards absence total
    notify_student          BOOLEAN NOT NULL DEFAULT TRUE,
                            -- send in-app notification when threshold hit
    notify_lecturer         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at              TIMESTAMP,
    updated_at              TIMESTAMP,

    UNIQUE (course_id)      -- one policy per course
);
```

**`warning_thresholds` JSON structure:**
```json
[
  { "level": 1, "value": 20, "label": "Warning" },
  { "level": 2, "value": 40, "label": "Serious Warning" },
  { "level": 3, "value": 60, "label": "Final Warning / Barred" }
]
```

- When `mode = 'percentage'`: value = % of total sessions absent
- When `mode = 'count'`: value = absolute number of absences

### 1.3 New Table: `attendance_warnings` (Log)

Tracks which warnings have been issued so they're not duplicated.

```sql
CREATE TABLE attendance_warnings (
    id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    course_id           BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    policy_level        TINYINT UNSIGNED NOT NULL,        -- matches level from thresholds
    absence_count       INT UNSIGNED NOT NULL,            -- absences at time of warning
    total_sessions      INT UNSIGNED NOT NULL,            -- total sessions at time of warning
    absence_percentage  DECIMAL(5,2) NOT NULL,
    created_at          TIMESTAMP,

    UNIQUE (course_id, user_id, policy_level),            -- one warning per level per student
    INDEX (user_id)
);
```

---

## 2. Model Design

### 2.1 `AttendanceExcuse` Model

```
App\Models\AttendanceExcuse
├── fillable: attendance_record_id, user_id, reason, category,
│             attachment_path, attachment_filename, status,
│             reviewed_by, reviewed_at, reviewer_note
├── casts: reviewed_at → datetime
├── relationships:
│   ├── record()    → BelongsTo AttendanceRecord
│   ├── user()      → BelongsTo User
│   └── reviewer()  → BelongsTo User (reviewed_by)
├── scopes:
│   └── scopePending($query), scopeForCourse($query, $courseId)
```

### 2.2 `AttendancePolicy` Model

```
App\Models\AttendancePolicy
├── fillable: course_id, tenant_id, mode, warning_thresholds,
│             bar_threshold, bar_action, include_late_as_absent,
│             notify_student, notify_lecturer
├── casts: warning_thresholds → array,
│          include_late_as_absent → boolean,
│          notify_student → boolean, notify_lecturer → boolean,
│          bar_threshold → decimal:2
├── relationships:
│   ├── course()  → BelongsTo Course
│   └── warnings() → HasMany AttendanceWarning
```

### 2.3 `AttendanceWarning` Model

```
App\Models\AttendanceWarning
├── fillable: course_id, user_id, policy_level, absence_count,
│             total_sessions, absence_percentage
├── relationships:
│   ├── course() → BelongsTo Course
│   └── user()   → BelongsTo User
```

### 2.4 Updated Relationships

**Course model** — add:
```php
public function attendancePolicy(): HasOne → AttendancePolicy
public function attendanceWarnings(): HasMany → AttendanceWarning
```

**AttendanceRecord model** — add:
```php
public function excuse(): HasOne → AttendanceExcuse
```

**User model** — add:
```php
public function attendanceExcuses(): HasMany → AttendanceExcuse
public function attendanceWarnings(): HasMany → AttendanceWarning
```

---

## 3. Service Layer

### 3.1 `AttendanceWarningService`

```
App\Services\Attendance\AttendanceWarningService
├── checkAndIssueWarnings(Course $course, User $student): void
│   - Called after each session ends (from AttendanceController::end)
│   - Calculates absence count/percentage across ALL ended sessions for the course
│   - Compares against policy thresholds
│   - Creates AttendanceWarning records for newly crossed thresholds
│   - Dispatches notifications if configured
│
├── getStudentWarningLevel(Course $course, User $student): ?int
│   - Returns highest warning level the student has reached
│
├── getAbsenceSummary(Course $course, User $student): array
│   - Returns: [absent_count, excused_count, total_sessions, rate, warning_level]
```

### 3.2 `AttendanceReportService`

```
App\Services\Attendance\AttendanceReportService
├── generateCourseReport(Course $course, ?Section $section): array
│   - Aggregates all sessions for the course (or filtered by section)
│   - Returns structured data: per-student summary + per-session detail
│
├── exportPdf(Course $course, ?Section $section): string
│   - Uses barryvdh/laravel-dompdf (already installed)
│   - Returns file path of generated PDF
│   - Includes: header (course info), summary table, per-session breakdown
│   - Color-coded attendance rates, warning flags
│
├── exportExcel(Course $course, ?Section $section): string
│   - Uses maatwebsite/excel (already installed)
│   - Sheet 1: Summary (student, present, late, absent, excused, rate%, warning level)
│   - Sheet 2: Detail (session date × student matrix)
│   - Returns file path
│
├── syncToCourseFiles(Course $course): void
│   - Generates combined PDF report
│   - Stores/replaces in "Attendance Records" default folder
│   - Creates CourseFile record linked to that folder
```

### 3.3 `AttendanceExcuseService`

```
App\Services\Attendance\AttendanceExcuseService
├── submit(AttendanceRecord $record, User $student, array $data): AttendanceExcuse
│   - Validates record belongs to student and status is 'absent'
│   - Handles file upload to storage
│   - Creates excuse record
│
├── approve(AttendanceExcuse $excuse, User $reviewer, ?string $note): void
│   - Updates excuse status to 'approved'
│   - Updates linked AttendanceRecord status to 'excused'
│   - Recalculates warnings (absence count decreased)
│
├── reject(AttendanceExcuse $excuse, User $reviewer, ?string $note): void
│   - Updates excuse status to 'rejected'
│   - Record stays as 'absent'
```

---

## 4. Controller & Route Design

### 4.1 New Routes

```php
// ── Student Attendance (student-facing) ──────────────────────
Route::get('/my-attendance', [StudentAttendanceController::class, 'index'])
    ->name('tenant.my-attendance');
Route::get('/my-attendance/course/{course}', [StudentAttendanceController::class, 'course'])
    ->name('tenant.my-attendance.course');

// ── Excuse Submission (student) ──────────────────────────────
Route::post('/my-attendance/excuse/{record}', [StudentAttendanceController::class, 'submitExcuse'])
    ->name('tenant.my-attendance.excuse.submit');

// ── Excuse Review (lecturer) ────────────────────────────────
Route::get('/attendance/excuses', [AttendanceExcuseController::class, 'index'])
    ->name('tenant.attendance.excuses');
Route::put('/attendance/excuses/{excuse}/approve', [AttendanceExcuseController::class, 'approve'])
    ->name('tenant.attendance.excuses.approve');
Route::put('/attendance/excuses/{excuse}/reject', [AttendanceExcuseController::class, 'reject'])
    ->name('tenant.attendance.excuses.reject');

// ── Attendance Policy (lecturer, per-course) ─────────────────
Route::get('/courses/{course}/attendance-policy', [AttendancePolicyController::class, 'edit'])
    ->name('tenant.courses.attendance-policy.edit');
Route::put('/courses/{course}/attendance-policy', [AttendancePolicyController::class, 'update'])
    ->name('tenant.courses.attendance-policy.update');

// ── Reports & Export (lecturer) ──────────────────────────────
Route::get('/attendance/report/{course}', [AttendanceReportController::class, 'show'])
    ->name('tenant.attendance.report');
Route::get('/attendance/report/{course}/pdf', [AttendanceReportController::class, 'downloadPdf'])
    ->name('tenant.attendance.report.pdf');
Route::get('/attendance/report/{course}/excel', [AttendanceReportController::class, 'downloadExcel'])
    ->name('tenant.attendance.report.excel');
Route::post('/attendance/report/{course}/sync-files', [AttendanceReportController::class, 'syncToCourseFiles'])
    ->name('tenant.attendance.report.sync');
```

### 4.2 Controller Responsibilities

**`StudentAttendanceController`** (new, student-facing)
- `index()` — List all enrolled courses with attendance summary cards
- `course($course)` — Detailed per-session attendance log for one course, with excuse submission forms
- `submitExcuse($record)` — Handle excuse form + file upload

**`AttendanceExcuseController`** (new, lecturer-facing)
- `index()` — List pending excuses across lecturer's courses (with filter tabs: pending/approved/rejected)
- `approve($excuse)` — Approve with optional note
- `reject($excuse)` — Reject with optional note

**`AttendancePolicyController`** (new, lecturer-facing)
- `edit($course)` — Show policy config form (or defaults if none set)
- `update($course)` — Save policy

**`AttendanceReportController`** (new, lecturer-facing)
- `show($course)` — Comprehensive attendance report page with charts and tables
- `downloadPdf($course)` — Stream PDF download
- `downloadExcel($course)` — Stream Excel download
- `syncToCourseFiles($course)` — Generate report and save into "Attendance Records" folder

---

## 5. UI/View Design

### 5.1 Student: My Attendance Page (`/my-attendance`)

```
┌─────────────────────────────────────────────────────────────┐
│  My Attendance                                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─── Course Card ──────────────────────────────────────┐   │
│  │ CSC1234 - Intro to Programming                       │   │
│  │ Section 01 · Dr. Ahmad                               │   │
│  │                                                       │   │
│  │  Present: 8    Late: 2    Absent: 1    Excused: 1    │   │
│  │  ███████████████████████░░░░  83% attendance          │   │
│  │                                                       │   │
│  │  ⚠ Warning Level 1: 20% absence threshold reached   │   │
│  │                                          [View →]    │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─── Course Card ──────────────────────────────────────┐   │
│  │ CSC2345 - Data Structures                            │   │
│  │ Section 02 · Dr. Siti                                │   │
│  │  ...                                          [View →]    │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 5.2 Student: Course Attendance Detail (`/my-attendance/course/{id}`)

```
┌─────────────────────────────────────────────────────────────┐
│  ← CSC1234 - My Attendance                                  │
├─────────────────────────────────────────────────────────────┤
│  Summary Bar:  Present: 8  |  Late: 2  |  Absent: 1        │
│  ███████████████████████░░░░  83%                           │
│                                                             │
│  ⚠ Warning: You have reached 17% absence.                  │
│    Policy requires minimum 80% attendance.                   │
├─────────────────────────────────────────────────────────────┤
│  Session Log                                                │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Week 12 · Lecture · 28 Mar 2026                      │   │
│  │ Status: ● Absent                                     │   │
│  │ ┌── Submit Excuse ───────────────────────────────┐   │   │
│  │ │ Category: [Medical        ▾]                    │   │   │
│  │ │ Reason:   [I was hospitalized for food...]      │   │   │
│  │ │ Attachment: [Choose file] MC_scan.pdf            │   │   │
│  │ │                              [Submit Excuse]    │   │   │
│  │ └────────────────────────────────────────────────┘   │   │
│  ├──────────────────────────────────────────────────────┤   │
│  │ Week 11 · Lecture · 21 Mar 2026                      │   │
│  │ Status: ● Present · 09:03 AM                         │   │
│  ├──────────────────────────────────────────────────────┤   │
│  │ Week 10 · Lecture · 14 Mar 2026                      │   │
│  │ Status: ● Excused                                    │   │
│  │ Excuse: Medical (Approved ✓) — "Hospital visit"      │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 5.3 Lecturer: Excuse Review Page (`/attendance/excuses`)

```
┌─────────────────────────────────────────────────────────────┐
│  Attendance Excuses                                         │
│  [Pending (3)]  [Approved]  [Rejected]                      │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Ahmad bin Ali · CSC1234 Sec01 · Week 12 Lecture      │   │
│  │ Category: Medical                                     │   │
│  │ Reason: "I was hospitalized for food poisoning"       │   │
│  │ Attachment: 📎 MC_scan.pdf [View]                     │   │
│  │ Submitted: 29 Mar 2026                                │   │
│  │                                                       │   │
│  │ Note: [optional note field             ]              │   │
│  │                      [✓ Approve]  [✗ Reject]          │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### 5.4 Lecturer: Attendance Policy (`/courses/{id}/attendance-policy`)

Accessed from the course show page as a new quick action or from within the attendance settings.

```
┌─────────────────────────────────────────────────────────────┐
│  ← CSC1234 · Attendance Policy                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Threshold Mode                                             │
│  (●) Percentage of total sessions                           │
│  ( ) Absolute number of absences                            │
│                                                             │
│  □ Count late attendance towards absence total               │
│                                                             │
│  Warning Thresholds                          [+ Add Level]  │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  Level 1:  [ 20 ] %   Label: [Warning           ]   │   │
│  │  Level 2:  [ 40 ] %   Label: [Serious Warning   ]   │   │
│  │  Level 3:  [ 60 ] %   Label: [Final Warning     ]   │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                             │
│  Barring Threshold (optional)                               │
│  [ 50 ] %                                                   │
│  Action: (●) Flag only  ( ) Notify via email  ( ) Block     │
│                                                             │
│  Notifications                                              │
│  ☑ Notify student when threshold reached                    │
│  ☑ Notify lecturer when student reaches threshold            │
│                                                             │
│                                     [Save Policy]           │
└─────────────────────────────────────────────────────────────┘
```

### 5.5 Lecturer: Comprehensive Report (`/attendance/report/{course}`)

```
┌─────────────────────────────────────────────────────────────┐
│  ← Attendance Report: CSC1234                               │
│                                                             │
│  Filter: [All Sections ▾]  Sessions: 12                     │
│                          [📄 PDF]  [📊 Excel]  [🔗 Save to │
│                                              Course Files]  │
├─────────────────────────────────────────────────────────────┤
│  Summary                                                    │
│  ┌────────┬─────────┬──────┬────────┬─────────┬──────────┐  │
│  │Student │ Present │ Late │ Absent │ Excused │ Rate  ⚠  │  │
│  ├────────┼─────────┼──────┼────────┼─────────┼──────────┤  │
│  │Ahmad   │   10    │  1   │   1    │   0     │ 92%      │  │
│  │Siti    │    8    │  2   │   2    │   0     │ 83%      │  │
│  │Muthu   │    5    │  1   │   4    │   2     │ 50%  ⚠3 │  │
│  └────────┴─────────┴──────┴────────┴─────────┴──────────┘  │
│                                                             │
│  Session Detail                                             │
│  ┌────────┬──────────┬───────┬───────┬───────┬───────┐      │
│  │        │ Wk1 Lec  │ Wk1 Tut│ Wk2 Lec│ ... │ Wk12  │     │
│  │        │ 5 Jan    │ 7 Jan  │ 12 Jan │     │ 28Mar │     │
│  ├────────┼──────────┼───────┼───────┼───────┼───────┤      │
│  │Ahmad   │    P     │   P   │   P   │  ...  │   A   │     │
│  │Siti    │    P     │   L   │   P   │  ...  │   P   │     │
│  │Muthu   │    A(E)  │   P   │   A   │  ...  │   A   │     │
│  └────────┴──────────┴───────┴───────┴───────┴───────┘      │
│                                                             │
│  P = Present, L = Late, A = Absent, E = Excused             │
└─────────────────────────────────────────────────────────────┘
```

### 5.6 Student Sidebar Update

Add "My Attendance" link under the **Learning** section:

```
Learning
  ├── My Courses
  ├── My Attendance    ← NEW (with attendance icon)
  ├── Course Materials
  └── Marks & Feedback
```

### 5.7 Course Show Page Update (Lecturer)

Add "Attendance Policy" as a quick action or inline setting on the course show page, alongside existing quick actions. Also add a link to the comprehensive report:

```
Quick Actions:
  Materials | Active Learning | Attendance | Quizzes | Groups | 📊 Att. Report
```

---

## 6. Workflow Diagrams

### 6.1 Student Excuse Submission Flow

```
Student views My Attendance → Course detail
  ↓
Sees session marked "Absent"
  ↓
Clicks "Submit Excuse" (inline expandable form)
  ↓
Fills: category (dropdown), reason (text), attachment (optional file)
  ↓
POST /my-attendance/excuse/{record}
  ↓
AttendanceExcuse created (status: pending)
  ↓
Lecturer gets notification badge on "Excuses" nav item
  ↓
Lecturer reviews → Approve or Reject
  ↓
If approved:
  ├── AttendanceRecord.status → 'excused'
  ├── Recalculate warning levels (may downgrade)
  └── Student notified
If rejected:
  ├── AttendanceRecord.status remains 'absent'
  └── Student notified with reviewer note
```

### 6.2 Warning Threshold Check Flow

```
AttendanceController::end() called (session ends)
  ↓
Absent students auto-created
  ↓
For each student in course:
  AttendanceWarningService::checkAndIssueWarnings()
    ↓
    Count total ended sessions for course (across all sections student is in)
    Count absence records (optionally including late if policy says so)
    ↓
    Calculate absence percentage
    ↓
    For each threshold in policy.warning_thresholds:
      If threshold crossed AND no existing AttendanceWarning for this level:
        ├── Create AttendanceWarning record
        ├── If notify_student: send database notification to student
        └── If notify_lecturer: send database notification to lecturer
```

### 6.3 Report Generation & Course Files Sync Flow

```
Lecturer navigates to /attendance/report/{course}
  ↓
AttendanceReportService::generateCourseReport()
  ├── Queries all ended sessions for the course
  ├── Builds per-student summary (present, late, absent, excused, %, warning level)
  └── Builds session × student matrix
  ↓
View renders summary table + detail matrix

── PDF Download ──
GET /attendance/report/{course}/pdf
  → AttendanceReportService::exportPdf()
  → Uses dompdf with Blade view (tenant.attendance.report-pdf)
  → Streamed download: "{course_code}_attendance_report.pdf"

── Excel Download ──
GET /attendance/report/{course}/excel
  → AttendanceReportService::exportExcel()
  → Uses maatwebsite/excel with CourseAttendanceExport class
  → Streamed download: "{course_code}_attendance_report.xlsx"

── Sync to Course Files ──
POST /attendance/report/{course}/sync-files
  → AttendanceReportService::syncToCourseFiles()
  → Generates PDF
  → Finds/creates "Attendance Records" CourseFolder
  → Creates/replaces CourseFile record
  → Stores file at: attendance-reports/{course_id}/report_{timestamp}.pdf
  → Redirects back with success message
```

---

## 7. File Structure (New Files)

```
app/
├── Http/Controllers/Tenant/
│   ├── StudentAttendanceController.php      (NEW)
│   ├── AttendanceExcuseController.php       (NEW)
│   ├── AttendancePolicyController.php       (NEW)
│   └── AttendanceReportController.php       (NEW)
├── Models/
│   ├── AttendanceExcuse.php                 (NEW)
│   ├── AttendancePolicy.php                 (NEW)
│   └── AttendanceWarning.php                (NEW)
├── Services/Attendance/
│   ├── AttendanceWarningService.php         (NEW)
│   ├── AttendanceReportService.php          (NEW)
│   └── AttendanceExcuseService.php          (NEW)
├── Exports/
│   └── CourseAttendanceExport.php           (NEW — maatwebsite/excel)
├── Notifications/
│   └── AttendanceWarningNotification.php    (NEW)
database/migrations/
│   ├── xxxx_create_attendance_excuses_table.php
│   ├── xxxx_create_attendance_policies_table.php
│   └── xxxx_create_attendance_warnings_table.php
resources/views/tenant/
│   ├── attendance/
│   │   ├── excuses/
│   │   │   └── index.blade.php              (NEW — lecturer excuse review)
│   │   ├── policy/
│   │   │   └── edit.blade.php               (NEW — lecturer policy config)
│   │   ├── report/
│   │   │   ├── show.blade.php               (NEW — comprehensive report page)
│   │   │   └── pdf.blade.php                (NEW — PDF template)
│   │   └── student/
│   │       ├── index.blade.php              (NEW — student attendance overview)
│   │       └── course.blade.php             (NEW — student per-course detail)
```

---

## 8. Modified Existing Files

| File | Change |
|------|--------|
| `app/Models/Course.php` | Add `attendancePolicy()` HasOne, `attendanceWarnings()` HasMany |
| `app/Models/AttendanceRecord.php` | Add `excuse()` HasOne relationship |
| `app/Models/User.php` | Add `attendanceExcuses()`, `attendanceWarnings()` HasMany |
| `app/Http/Controllers/Tenant/AttendanceController.php` | Call `AttendanceWarningService::checkAndIssueWarnings()` in `end()` |
| `routes/web.php` | Add all new routes (see Section 4.1) |
| `resources/views/layouts/partials/student-sidebar.blade.php` | Add "My Attendance" nav link |
| `resources/views/layouts/partials/bottom-nav.blade.php` | Consider adding attendance icon |
| `resources/views/tenant/courses/show.blade.php` | Add "Att. Report" + "Policy" quick actions |
| `resources/views/layouts/partials/sidebar.blade.php` | Add "Excuses" badge to Attendance nav |
| `config/lectura.php` | Add default policy values under `attendance` key |

---

## 9. Validation Rules

### Excuse Submission
```php
'reason'     => ['required', 'string', 'max:2000'],
'category'   => ['required', 'in:medical,family_emergency,academic_conflict,official_duty,other'],
'attachment'  => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
```

### Policy Update
```php
'mode'                   => ['required', 'in:percentage,count'],
'warning_thresholds'     => ['required', 'array', 'min:1', 'max:5'],
'warning_thresholds.*.level' => ['required', 'integer', 'min:1', 'max:5'],
'warning_thresholds.*.value' => ['required', 'numeric', 'min:1', 'max:100'],
'warning_thresholds.*.label' => ['required', 'string', 'max:50'],
'bar_threshold'          => ['nullable', 'numeric', 'min:1', 'max:100'],
'bar_action'             => ['required', 'in:flag,notify,block'],
'include_late_as_absent' => ['boolean'],
'notify_student'         => ['boolean'],
'notify_lecturer'        => ['boolean'],
```

---

## 10. Security & Authorization

| Action | Who Can Do It |
|--------|--------------|
| Submit excuse | Student who owns the absent record |
| Review excuse | Lecturer who owns the course (or admin) |
| Configure policy | Lecturer who owns the course (or admin) |
| View reports | Lecturer who owns the course (or admin) |
| Download PDF/Excel | Lecturer who owns the course (or admin) |
| Sync to course files | Lecturer who owns the course (or admin) |
| View own attendance | Student enrolled in the course |

File uploads stored at `attendance-excuses/{record_id}/` with randomized filename. Not publicly accessible — served through controller with auth check.

---

## 11. Implementation Order

**Phase 1 — Core Infrastructure**
1. Migrations (3 tables)
2. Models (3 new + relationship updates on 3 existing)
3. AttendanceExcuseService
4. AttendanceWarningService

**Phase 2 — Student Experience**
5. StudentAttendanceController + views (My Attendance index + course detail)
6. Excuse submission form + file upload
7. Student sidebar navigation update
8. Warning display on student pages

**Phase 3 — Lecturer Tools**
9. AttendancePolicyController + policy edit view
10. AttendanceExcuseController + excuse review view
11. Warning integration in AttendanceController::end()
12. Excuse badge in lecturer sidebar

**Phase 4 — Reports & Export**
13. AttendanceReportService
14. AttendanceReportController + report view
15. PDF export (dompdf template)
16. Excel export (maatwebsite/excel)
17. Sync to Course Files

**Phase 5 — Polish**
18. Notifications (database channel)
19. Course show page quick action updates
20. Mobile-responsive testing
