# Lectura Go — Lecturer API

Base URL: `/api/v1/t/{tenant}/` (all paths below are relative to it).
Auth: `Authorization: Bearer <sanctum token>`, `Accept: application/json`. Optional `X-Lectura-Role` header selects the active role for multi-role users.

Source: `routes/api/v1/lecturer.php` → `App\Http\Controllers\Api\V1\Lecturer\*`. Tests: `tests/Feature/Api/V1/Lecturer/*`.
Examples below are real responses captured from the test suite (ids/dates are illustrative).

## Conventions

- Reads: `{"data": ...}`. Actions: `{"message": "...", "data": {...}}`.
- Timestamps: ISO-8601 in the server timezone (`2026-09-08T10:18:00+00:00`). Schedule times are `"HH:mm"` strings.
- Errors (production shape — local debug builds add `exception`/`trace`, ignore them):
  - `401` `{"message": "Unauthenticated."}`
  - `403` `{"message": "<reason>"}` — reasons used here:
    - `This area is for lecturers.` — **every endpoint**, when the active role is `student`
    - `You do not have access to this course.` / `... this section.` / `... this attendance session.`
  - `404` — unknown id **or a record from another institution** (route-bound models are tenant-scoped). Message may be Laravel's default (`No query results for model ...`) or `Course not found.` / `Section not found.` / `Attendance record not found.` / `This student is not enrolled in this section.`
  - `409` `{"message": "...", "data"?: {...}}` — state conflicts (documented per endpoint)
  - `422` `{"message": "...", "errors": {"field": ["..."]}}`
- Enums: `session_type` ∈ `lecture|tutorial|lab|extra|replacement`; attendance `status` ∈ `present|late|absent|excused`; record `method` ∈ `qr_scan|manual`; `enrollment_method` ∈ `manual|csv|invite_code`; schedule `type` ∈ `lecture|tutorial|lab|other`; `day` ∈ `monday..sunday`.

## Access rules (mirrors the web app)

- **Accessible courses** = courses I own (`lecturer_id`) + courses where I'm assigned to at least one section. Tenant admins can access every course.
- **Section access** (section endpoints, starting attendance) = section lecturer, course owner, or tenant admin.
- **Session access** = the lecturer who started it, a lecturer of its section, the course owner, or a tenant admin.
- Attendance index / dashboard active sessions / wheel use the web's "accessible sections": sections assigned to me + unassigned sections of courses I own (admins: all).

---

## Dashboard

### GET `lecturer/dashboard`

Same figures as the web lecturer dashboard. `today_schedule` is sorted by `start_time`; `is_now`/`is_past` computed on the server clock. `active_sessions` lets the app resume a live QR. `recent_courses` = 5 most recently created accessible courses. `avg_attendance` is an int percentage or `null` (no ended sessions).

```json
{
  "data": {
    "tenant_name": "Universiti Teknologi Malaysia",
    "date": "2026-09-08",
    "day": "Tuesday",
    "server_time": "2026-09-08T10:30:00+00:00",
    "stats": { "active_courses": 1, "students": 3, "avg_attendance": 67 },
    "today_schedule": [
      {
        "course": { "id": 1, "code": "SKMM3013", "title": "Thermodynamics" },
        "section": { "id": 1, "name": "Section 01" },
        "start_time": "10:00",
        "end_time": "12:00",
        "location": "BK-2",
        "type": "lecture",
        "is_now": true,
        "is_past": false
      }
    ],
    "active_sessions": [ /* AttendanceSession summary, see below */ ],
    "recent_courses": [ /* CourseSummary, see below */ ]
  }
}
```

---

## Courses & sections

### GET `lecturer/courses`

All accessible courses, newest first (not paginated — mirrors the web list).

**CourseSummary**
```json
{
  "data": [
    {
      "id": 1,
      "code": "SKMM3013",
      "title": "Thermodynamics",
      "status": "active",
      "status_label": "Active",
      "status_color": "emerald",
      "teaching_mode": "face_to_face",
      "num_weeks": 14,
      "credit_hours": 3,
      "sections_count": 2,
      "academic_term": null,
      "faculty": null
    }
  ]
}
```
`status` ∈ `draft|active|inactive|archived` (labels Draft/Active/Inactive/Archived, colors amber/emerald/red/slate). `academic_term`/`faculty` are `{"id","name"}` or `null`.

### GET `lecturer/courses/{course}`

CourseSummary fields plus details. Course owners (and admins) get **all** sections and the course `invite_code` (share with co-lecturers only); section-assigned lecturers get only their sections and `invite_code: null`. Sections ordered by creation.

```json
{
  "data": {
    "id": 1,
    "code": "SKMM3013",
    "title": "Thermodynamics",
    "status": "active",
    "status_label": "Active",
    "status_color": "emerald",
    "teaching_mode": "face_to_face",
    "num_weeks": 14,
    "credit_hours": 3,
    "sections_count": 2,
    "academic_term": null,
    "faculty": null,
    "description": "Energy, entropy and power cycles.",
    "format": ["lecture", "tutorial"],
    "is_owner": true,
    "invite_code": "4PJJEZMH",
    "total_students": 3,
    "programme": null,
    "learning_outcomes": [ { "id": 1, "code": "CLO1", "description": "Apply the first law..." } ],
    "topics": [ { "id": 1, "week_number": 1, "title": "Introduction" } ],
    "sections": [
      {
        "id": 1,
        "name": "Section 01",
        "code": "01",
        "invite_code": "K7Q2M9XA",
        "capacity": 40,
        "is_active": true,
        "schedule": [
          { "day": "tuesday", "start_time": "10:00", "end_time": "12:00", "location": "BK-2", "type": "lecture" },
          { "day": "thursday", "start_time": "14:00", "end_time": "16:00", "location": "Lab 3", "type": "lab" }
        ],
        "academic_term": null,
        "lecturers": [],
        "active_students_count": 3,
        "active_session_id": 2
      },
      {
        "id": 2,
        "name": "Section 02",
        "code": "02",
        "invite_code": "P3VD8R1Z",
        "capacity": null,
        "is_active": true,
        "schedule": [],
        "academic_term": null,
        "lecturers": [ { "id": 2, "name": "Dr Farah Ahmad" } ],
        "active_students_count": 0,
        "active_session_id": null
      }
    ]
  }
}
```
`section.invite_code` is the **student** enrolment code. `total_students` counts distinct active students across all sections of the course. `active_session_id` = the section's running attendance session (or `null`).
Errors: 403 course access, 404 other tenant / unknown.

### GET `lecturer/courses/{course}/sections/{section}`

Section fields (as in the course detail) plus `course` and the active roster sorted by name (not paginated; bounded by section size).

```json
{
  "data": {
    "id": 1,
    "name": "Section 01",
    "code": "01",
    "invite_code": "K7Q2M9XA",
    "capacity": 40,
    "is_active": true,
    "schedule": [ { "day": "tuesday", "start_time": "10:00", "end_time": "12:00", "location": "BK-2", "type": "lecture" } ],
    "academic_term": null,
    "lecturers": [],
    "active_students_count": 3,
    "active_session_id": 2,
    "course": { "id": 1, "code": "SKMM3013", "title": "Thermodynamics" },
    "students": [
      {
        "id": 3,
        "name": "Aina Sofea",
        "email": "aina@graduate.utm.my",
        "student_id_number": "A21EC0001",
        "enrollment_method": "manual",
        "enrolled_at": "2026-09-08T10:30:00+00:00"
      }
    ]
  }
}
```
Errors: 404 when the section does not belong to `{course}`; 403 section access.

### POST `lecturer/courses/{course}/sections/{section}/toggle-active`

No body. Flips `is_active` (inactive sections don't accept invite-code enrolment and aren't offered for attendance).
```json
{ "message": "Section 'Section 02' deactivated.", "data": { "id": 2, "is_active": false } }
```

### POST `lecturer/courses/{course}/sections/{section}/students`

Body: `name` (required, ≤255), `email` (required, email), `student_id_number` (optional, ≤50).
Finds or creates the user by email, ensures a `student` membership in the institution, enrols them. A previously removed student is re-activated. → **201**
```json
{
  "message": "Arif Danial added to Section 01.",
  "data": {
    "student": {
      "id": 6,
      "name": "Arif Danial",
      "email": "arif@graduate.utm.my",
      "student_id_number": "A21EC0004",
      "enrollment_method": "manual",
      "enrolled_at": "2026-09-08T10:30:00+00:00"
    }
  }
}
```
Errors: 422 validation; 422 already enrolled:
```json
{ "message": "Arif Danial is already enrolled in this section.", "errors": { "email": ["Arif Danial is already enrolled in this section."] } }
```

### DELETE `lecturer/courses/{course}/sections/{section}/students/{user}`

Deactivates the enrolment (history kept).
```json
{ "message": "Student removed from section.", "data": { "section_id": 1, "user_id": 5 } }
```
Errors: 404 `This student is not enrolled in this section.`

---

## Attendance

### AttendanceSession summary (used in lists)

```json
{
  "id": 2,
  "status": "active",
  "is_active": true,
  "is_locked": false,
  "lock_reason": null,
  "session_type": "lecture",
  "week_number": 3,
  "started_at": "2026-09-08T10:18:00+00:00",
  "ended_at": null,
  "course": { "id": 1, "code": "SKMM3013", "title": "Thermodynamics" },
  "section": { "id": 1, "name": "Section 01", "code": "01" },
  "counts": { "present": 1, "late": 0, "absent": 0, "excused": 0, "checked_in": 1 },
  "total_students": 4
}
```
`checked_in` = present + late. `total_students` = active students in the section. Absent records only exist after the session ends.

`is_locked` = the session's attendance can no longer be changed; hide reopen, edit, override and delete. `lock_reason`:
- `semester_closed` — the course is archived (its semester was closed). Unlocks when the semester is reopened.
- `edit_window_passed` — the session ended more than `ATTENDANCE_LOCK_AFTER_DAYS` (default 14) days ago.

Reopen, update, override and delete on a locked session return
409 `{"message": "...", "data": {"lock_reason": "semester_closed"}}`.

### AttendanceSession detail (show / start / update / reopen / end)

Summary fields plus:
```json
{
  "qr_mode": "rotating",
  "qr_rotation_seconds": 30,
  "late_threshold_minutes": 15,
  "duration_minutes": 12,
  "records": [
    {
      "id": 4,
      "user": { "id": 3, "name": "Aina Sofea", "email": "aina@graduate.utm.my" },
      "student_id_number": "A21EC0001",
      "status": "present",
      "method": "qr_scan",
      "checked_in_at": "2026-09-08T10:30:00+00:00",
      "override": null,
      "excuse": null
    }
  ],
  "not_checked_in": [
    { "id": 4, "name": "Zul Hakim", "student_id_number": "A21EC0002" }
  ]
}
```
- `records`: every record for the session, sorted by student name. `override` = `{"by": {"id", "name"}, "reason"}` when a lecturer changed it. `excuse` = `{"id", "status", "category"}` or `null`.
- `not_checked_in`: active students without a record — only filled while the session is active (empty once ended, because absentees become records).
- `duration_minutes`: started → ended (or now).

### GET `lecturer/attendance`

```json
{
  "data": {
    "active_sessions": [ /* summary */ ],
    "recent_sessions": [ /* summary, status "ended", newest first, max 30 */ ],
    "sections": [
      {
        "id": 1,
        "name": "Section 01",
        "code": "01",
        "course": { "id": 1, "code": "SKMM3013", "title": "Thermodynamics" },
        "active_session_id": 2
      }
    ],
    "session_types": ["lecture", "tutorial", "lab", "extra", "replacement"]
  }
}
```
`sections` = active accessible sections (sorted by course code, section name) for the "start session" picker; `active_session_id` non-null means tapping should resume that session instead of starting one.

### POST `lecturer/attendance/start`

Body: `section_id` (required), `session_type` (required, enum), `week_number` (optional int ≥1).
→ **201** `{"message": "Attendance session started.", "data": <detail>}` (rotating QR, rotation/late threshold from server config).

Errors:
- 409 active session already running for the section — resume it:
  ```json
  { "message": "An active session already exists for this section.", "data": { "session_id": 2 } }
  ```
- 409 `{"message": "This course is archived, so no new attendance sessions can be started. Reopen the semester first."}`
- 422 validation (`section_id`, `session_type`, `week_number`)
- 404 section of another institution; 403 no section access

### GET `lecturer/attendance/{session}`

→ `{"data": <detail>}`

### GET `lecturer/attendance/{session}/token`

Poll this on the live QR screen. Render `payload` **verbatim** as the QR code (students' check-in validates it). Re-fetch when `expires_in` reaches 0 (the previous token stays valid for one extra window as grace), and poll every ~5 s for new check-ins (records newest first).

```json
{
  "data": {
    "session_id": 2,
    "payload": "{\"s\":2,\"t\":\"d7eef9d96efe33cab87f3a182d484c6daaa96b4bf5cce9d752e6a6c149fbce7a\",\"ts\":1789136970}",
    "qr_mode": "rotating",
    "rotation_seconds": 30,
    "expires_in": 30,
    "server_time": "2026-09-08T10:30:00+00:00",
    "checked_in": 1,
    "total": 4,
    "records": [
      {
        "id": 4,
        "user_id": 3,
        "name": "Aina Sofea",
        "status": "present",
        "time": "10:30:00",
        "checked_in_at": "2026-09-08T10:30:00+00:00"
      }
    ]
  }
}
```
`expires_in` = seconds left in the current rotation window (1…rotation_seconds). Show "X just checked in!" when `checked_in` grows (`records[0]` is the newest).
Errors: 409 `{"message": "This attendance session has ended."}` → leave the QR screen.

### POST `lecturer/attendance/{session}/end`

No body. Ends the session, creates `absent` records for enrolled students who didn't check in, runs attendance-warning checks.
```json
{
  "message": "Session ended. 3 students marked absent.",
  "data": { "marked_absent": 3, "session": <detail> }
}
```
Errors: 409 `{"message": "This session has already ended."}`

### POST `lecturer/attendance/{session}/reopen`

No body. Deletes auto-generated absences (keeps real scans and lecturer overrides) and makes the session active again.
→ `{"message": "Session reopened. Students can now scan again.", "data": <detail>}`

Errors:
- 409 `{"message": "Only ended sessions can be reopened."}`
- 409 session is locked (see `is_locked`)
- 409 another session is active for the section:
  ```json
  { "message": "Another attendance session is already active for this section.", "data": { "session_id": 2 } }
  ```

### PUT `lecturer/attendance/{session}`

Body: `session_type` (required, enum), `week_number` (nullable int ≥1).
→ `{"message": "Session details updated.", "data": <detail>}` · 422 validation.

### PUT `lecturer/attendance/{session}/records/{record}`

Manual override. Body: `status` (required: `present|late|absent|excused`), `reason` (optional, ≤255).
```json
{
  "message": "Attendance updated.",
  "data": {
    "id": 2,
    "user": { "id": 4, "name": "Zul Hakim", "email": "zul@graduate.utm.my" },
    "student_id_number": "A21EC0002",
    "status": "excused",
    "method": "qr_scan",
    "checked_in_at": "2026-09-08T10:30:00+00:00",
    "override": { "by": { "id": 1, "name": "Dr Hidayat Mat" }, "reason": "Medical certificate" },
    "excuse": null
  }
}
```
Errors: 404 `Attendance record not found.` (record belongs to a different session); 422 validation. Overrides only apply to existing records (checked-in students while active; everyone after the session ends).

### DELETE `lecturer/attendance/{session}`

Deletes an **ended** session and its records.
→ `{"message": "Attendance session deleted.", "data": {"id": 1}}`
Errors: 409 `{"message": "Cannot delete an active session. End it first."}`

---

## Random present-student wheel

The spin (random pick, animation, removing winners, history) happens on the phone.

### GET `lecturer/wheel`

Pickers + defaults (the most recent session across my sections). Sections per course follow the web wheel: sections assigned to me + unassigned sections of courses I own.
```json
{
  "data": {
    "courses": [
      {
        "id": 1,
        "code": "SKMM3013",
        "title": "Thermodynamics",
        "sections": [ { "id": 1, "name": "Section 01", "code": "01", "is_active": true } ]
      }
    ],
    "defaults": { "course_id": 1, "section_id": 1, "session_id": 2 }
  }
}
```
`defaults` is `null` when there are no sessions.

### GET `lecturer/wheel/sessions?section_id={id}`

Latest 20 sessions of the section, newest first.
```json
{
  "data": [
    {
      "id": 2,
      "label": "LIVE — W3 Lecture — 08 Sep 2026, 10:18",
      "session_type": "lecture",
      "week_number": 3,
      "started_at": "2026-09-08T10:18:00+00:00",
      "is_active": true,
      "checked_in": 1
    }
  ]
}
```
Errors: 422 `section_id` required; 404 section of another institution; 403 course access.

### GET `lecturer/wheel/present-students?session_id={id}&include_late=1`

`include_late` optional (`1`/`true` includes `late` students; default present only).
```json
{
  "data": {
    "students": [ { "id": 3, "name": "Aina Sofea", "status": "present" } ],
    "session": {
      "id": 2,
      "week_number": 3,
      "session_type": "lecture",
      "started_at": "2026-09-08T10:18:00+00:00",
      "is_active": true,
      "section_name": "Section 01",
      "course_code": "SKMM3013"
    }
  }
}
```
Errors: 422 `session_id` required; 404 session of another institution; 403 course access.

---

## Differences from the web app (intentional)

- Section endpoints (show, toggle, add/remove student) now check course **and** section access; the web SectionController had no authorization.
- Record override checks that the lecturer can access the session and that the record belongs to it (web did neither).
- Adding a previously removed student re-activates the enrolment instead of failing with "already enrolled".
- `end` on an ended session and `reopen` while another session is active for the section return 409 instead of silently proceeding.
- Token on an ended session returns 409 (web: 403 `{"error": "Session ended"}`).
