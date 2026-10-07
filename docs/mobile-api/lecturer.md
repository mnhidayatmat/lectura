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

Same figures as the web lecturer dashboard. `today_schedule` is sorted by `start_time`; `is_now`/`is_past` computed on the server clock. `active_sessions` lets the app resume a live QR; sessions of deactivated sections are left out. `recent_courses` = 5 most recently created accessible courses. `avg_attendance` is an int percentage or `null` (no ended sessions).

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
`status` ∈ `draft|active|inactive|archived` (labels Draft/Active/Inactive/Archived, colors amber/emerald/red/slate). `academic_term`/`faculty` are `{"id","name"}` or `null`. An `active` course whose term's `end_date` has passed reports `status_label` "Ended" (amber) and `term_ended: true`; `status` stays `active`. Otherwise an `active` course with no active section in the current semester (a section's own term, falling back to the course's; with no sections, the course's term) reports `status_label` "Inactive" (red) and `not_running: true`; `status` again stays `active`.

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
`section.invite_code` is the **student** enrolment code. `total_students` counts distinct active students across all sections of the course. `active_session_id` = the section's running attendance session (or `null`; always `null` for an inactive section, which stays listed so it can be switched back on).
Errors: 403 course access, 404 other tenant / unknown.

### POST `lecturer/courses/join`

Takes over a course by its invite code, as `courses/join` does on the web (the course's `lecturer_id`
becomes me). Body `{"invite_code": "SKM1001X"}` — uppercased and stripped of anything but letters
and digits, so `skm1001-x` works.
→ `{"message": "You joined SKM1001 — Statics.", "data": {"id": 12, "code": "SKM1001", "title": "Statics", "already_joined": false}}`
Already the lecturer: 200 with `already_joined: true`. Errors: 422 `invite_code` (unknown code);
403 `Only lecturers can join a course.` when I hold neither `lecturer` nor `admin` in the tenant.

### GET `lecturer/courses/{course}/attendance-policy`

```json
{
  "data": {
    "exists": false,
    "mode": "percentage",
    "warning_thresholds": [
      { "level": 1, "value": 20, "label": "Warning" },
      { "level": 2, "value": 40, "label": "Serious Warning" }
    ],
    "bar_threshold": null,
    "bar_action": "flag",
    "include_late_as_absent": false,
    "notify_student": true,
    "notify_lecturer": true
  }
}
```
`exists: false` means the course has no policy yet and these are the web form's defaults. `mode`
∈ `percentage|count`: thresholds are a % of ended sessions missed, or a number of absences.
`bar_action` ∈ `flag|notify|block`.

### PUT `lecturer/courses/{course}/attendance-policy`

Body: the same fields as above minus `exists` — the web's validation (1–5 thresholds, `level`
1–5, `value` 1–100, `label` ≤ 50; `bar_threshold` nullable 1–100). Thresholds are stored sorted by
level. → `{"message": "Attendance policy saved.", "data": {policy}}`. Course access as for `show`.

### GET `lecturer/courses/{course}/sections/{section}`

Section fields (as in the course detail) plus `course` and the active roster sorted by name (not paginated; bounded by section size). As there, `active_session_id` is always `null` for an inactive section; the same goes for the section in every section create/update response.

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

No body. Flips `is_active` (inactive sections don't accept invite-code enrolment and aren't offered for attendance). Deactivating ends any attendance session still running for the section (no-shows marked absent) and the message says so, e.g. "Section 'Section 02' deactivated. Ended 1 running attendance session.".
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
- `semester_closed` — the section's semester was closed (Semesters page). Unlocks when the semester is reopened.
- `course_archived` — the course itself is archived. Unlocks when the course is restored.
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
  ],
  "episode_watch": {
    "episode": { "id": 7, "episode_number": 1, "title": "Titis Leaves Home" },
    "students": [
      { "user_id": 3, "status": "finished", "watched_percent": 100, "last_watched_at": "2026-09-07T21:14:00+00:00" },
      { "user_id": 4, "status": "not_started", "watched_percent": 0, "last_watched_at": null }
    ]
  }
}
```
- `records`: every record for the session, sorted by student name. `override` = `{"by": {"id", "name"}, "reason"}` when a lecturer changed it. `excuse` = `{"id", "status", "category"}` or `null`.
- `not_checked_in`: active students without a record — only filled while the session is active (empty once ended, because absentees become records).
- `duration_minutes`: started → ended (or now).
- `episode_watch` (or `null`): the course's published Watch episode for the session's `week_number`
  (lowest episode number when there are several), and every active student of the section with
  their watch state, so the screen can show "watched" beside attendance. `null` when the session has
  no week or the week has no episode. `status` ∈ `not_started|watching|finished`.

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
Sessions of deactivated sections are left out of `active_sessions` and `recent_sessions` too.

### POST `lecturer/attendance/start`

Body: `section_id` (required), `session_type` (required, enum), `week_number` (optional int ≥1).
→ **201** `{"message": "Attendance session started.", "data": <detail>}` (rotating QR, rotation/late threshold from server config).

Errors:
- 409 active session already running for the section — resume it:
  ```json
  { "message": "An active session already exists for this section.", "data": { "session_id": 2 } }
  ```
- 409 `{"message": "This course is archived, so no new attendance sessions can be started."}`
- 409 `{"message": "This section is inactive, so no new attendance sessions can be started."}`
- 409 `{"message": "This section's semester is closed, so no new attendance sessions can be started."}`
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

## Assignment marking

Access: the assignment's course, by the course rule above. Submission and file ids are checked
against the assignment (404 otherwise). Group assignments: every member holds a copy of the
leader's submission (the leader's alone carries the files); lists show one row per group — the
copy with the files — and marking it marks every copy.

### GET `lecturer/assignments?course_id={id}`

Assignments in my accessible courses (`course_id` optional), latest deadline first.
```json
{
  "data": [
    {
      "id": 7, "title": "Lab Report 1", "type": "individual", "status": "published",
      "total_marks": 20, "deadline": "2026-09-30T23:59:00+08:00",
      "marking_mode": "manual", "submission_type": "both",
      "parent_id": null, "sub_assignments_count": 0,
      "course": { "id": 1, "code": "SKMM3013", "title": "Thermodynamics" },
      "counts": { "submissions": 12, "graded": 5 }
    }
  ]
}
```

### GET `lecturer/assignments/{assignment}`

The summary above plus `description`, `rubric` and `submissions` (newest first):
```json
{
  "rubric": { "criteria": [ { "id": 3, "title": "Method", "description": null, "max_marks": 10,
    "levels": [ { "label": "Excellent", "description": "...", "marks": 10 } ] } ] },
  "submissions": [
    {
      "id": 41, "status": "submitted", "is_late": false, "submitted_at": "...", "files_count": 1,
      "student": { "id": 3, "name": "Aina Sofea", "student_id_number": "A21EM0001" },
      "group": null,
      "mark": { "total_marks": 15, "max_marks": 20, "percentage": 75, "is_final": true, "finalized_at": "..." }
    }
  ]
}
```
`rubric` is `null` when the assignment has none. Submission `status` ∈ `submitted|ai_processing|ai_completed|graded`.

### GET `lecturer/assignments/{assignment}/submissions/{submission}`

The submission row plus `notes`, `text_content`, `files` (`id, name, mime_type, size_bytes,
has_annotations`), `feedback` (`strengths, improvements, is_released` or `null`), `suggestions`
(the web's AI-marking suggestions, if any: `rubric_criteria_id, suggested_marks, max_marks,
explanation`), `group_members` (`id, name` — everyone the mark will reach) and `assignment` (the
summary with its `rubric`).

### GET `lecturer/assignments/{assignment}/submissions/{submission}/files/{file}`

The file as a download. 404 `This file is not stored on Lectura. Open it on the web.` for Drive-only copies.

### POST `lecturer/assignments/{assignment}/submissions/{submission}/mark`

Saves **and releases** in one step, as the web's finalize does. Body:
- with a rubric: `criteria` = `{ "<criterion id>": marks, ... }` — every criterion required, each 0…its `max_marks`;
- without: `total` 0…`total_marks`;
- `feedback_strengths`, `feedback_improvements` optional (≤ 5000).

Writes a final `StudentMark` per member (total = the criteria's sum, which must not exceed
`total_marks`), sets the submissions to `graded`, releases feedback when either text is given and
sends `FeedbackReleased` to each member.
→ `{"message": "Marks released to Aina Sofea.", "data": {"total_marks": 15, "max_marks": 20, "percentage": 75, "members_marked": 1}}`

---

## Assessment marking

Course assessment-plan items (tests, projects …), separate from assignments. Access: the
assessment's course by the course rule; the roster is always **my sections** only (the web's
"lecturer sections": admin all, owner own + unassigned, section lecturer own).

### GET `lecturer/courses/{course}/assessments`

Top-level assessments in plan order, each with `children` (its parts). `meta.students` = my roster size.
```json
{
  "data": [
    {
      "id": 5, "title": "Test 1", "type": "test", "status": "active",
      "total_marks": 50, "weightage": 20, "due_date": null,
      "requires_submission": false, "is_group": false, "parent_id": null,
      "counts": { "submissions": 0, "graded": 12, "released": 12 },
      "children": []
    }
  ],
  "meta": { "students": 40 }
}
```
A parent is marked through its children (nothing rolls child scores up); mark leaf items only.

### GET `lecturer/assessments/{assessment}`

Summary plus `course`, `description`, `rubric` (`is_weighted`, `criteria` with `weightage` and
`levels`), `stats` (`students, submitted, graded, released, average_percentage`) and `students`:
```json
{
  "student": { "id": 3, "name": "Aina Sofea", "student_id_number": "A21EM0001" },
  "section": { "id": 1, "name": "Section 01" },
  "group": { "id": 2, "name": "Team 1", "is_leader": true },
  "submission": { "id": 9, "status": "submitted", "is_late": false, "submitted_at": "...", "files_count": 2 },
  "score": {
    "id": 11, "raw_marks": 40, "max_marks": 50, "percentage": 80, "weighted_marks": 16,
    "criteria_marks": { "3": 8 }, "feedback": "Good", "is_computed": false,
    "is_released": false, "released_at": null, "finalized_at": "..."
  }
}
```
Sorted by name; `group`, `submission`, `score` may be `null`.

### GET `lecturer/assessments/{assessment}/submissions/{submission}` · GET `.../files/{file}[?original=1]`

The submission (`notes`, `files` with `is_stamped`) and a file download. The file is the
grade-stamped copy when one exists; `original=1` gives the upload.

### PUT `lecturer/assessments/{assessment}/scores/{user}`

Body: `raw_marks` 0…`total_marks`, or with a rubric `criteria_marks` `{ "<criterion id>": marks }`
(every criterion, each ≤ its max); `feedback` optional ≤ 5000. The rubric total follows the web:
weighted — Σ (score/max) × (weight/Σweights) × total_marks — only when every criterion has a
positive weight, otherwise the plain sum, capped at `total_marks`. `weighted_marks` = percentage ×
weightage / 100. A group submitter's mark goes to every member's copy. **Saving always leaves the
score unreleased** (as per-submission marking does on the web).
→ `{"message": "Mark saved. Release it when you are ready.", "data": {score}}`
Errors: 404 student not on my roster; 422 `This assessment is marked through its parts.` for a parent.

### POST `lecturer/assessments/{assessment}/release`

Body `{"score_ids": [..]}` optional. Releases marked, unreleased scores of my roster — all of
them, or those ids widened to the rest of each group — and sends `AssessmentMarksReleased`.
→ `{"message": "Marks released to 12 students.", "data": {"released": 12}}`

### POST `lecturer/assessments/{assessment}/scores/{score}/unrelease`

Retracts the score and its group's. → `{"message": "Marks retracted.", "data": {"retracted": 2, "score": {score}}}`

Not in the app (still web): pen annotations, answer-script upload to Drive, compute-from-items,
linking items, and the web's AI marking (which currently only writes placeholder suggestions).

---

## Absence excuses

Students submit these from `student/attendance/records/{record}/excuse`. Scope: excuses on sessions
of my accessible sections (same rule as the attendance index).

### GET `lecturer/excuses?status=pending|approved|rejected|all&page=1`

`status` defaults to `pending`. 20 per page, newest first.
```json
{
  "data": [
    {
      "id": 4,
      "status": "pending",
      "category": "medical",
      "category_label": "Medical",
      "reason": "Hospital appointment",
      "has_attachment": true,
      "attachment_filename": "mc.pdf",
      "submitted_at": "2026-09-08T12:00:00+00:00",
      "reviewed_at": null,
      "reviewer_note": null,
      "reviewer": null,
      "student": { "id": 3, "name": "Aina Sofea", "email": "aina@example.com", "student_id_number": "A21EM0001" },
      "record": { "id": 9, "status": "absent" },
      "session": { "id": 2, "session_type": "lecture", "week_number": 3, "started_at": "2026-09-08T10:18:00+00:00" },
      "section": { "id": 1, "name": "Section 01" },
      "course": { "id": 1, "code": "SKMM3013", "title": "Thermodynamics" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "pending_count": 1 }
}
```
`pending_count` ignores the `status` filter, for a badge.

### POST `lecturer/excuses/{excuse}/approve` · POST `lecturer/excuses/{excuse}/reject`

Body `{"note": "..."}` optional (≤ 500). Approving sets the record to `excused` and re-runs the
course's attendance warnings, as the web does. → `{"message": "...", "data": {excuse}}`.
Errors: 422 `This excuse has already been approved.` (or `rejected`); 403 without session access;
404 for another institution's excuse.

### GET `lecturer/excuses/{excuse}/attachment`

The file as a download. 404 `This excuse has no attachment.`

---

## Course & section editing

### GET `lecturer/course-options`

Pickers: `academic_terms` (`id, name, is_default`, newest first), `faculties`, `programmes`
(`id, name, code, faculty_id`), `lecturers` (active lecturer/admin/coordinator staff of this
institution: `id, name, email`), `teaching_modes` (`face_to_face|online|hybrid`), `formats`
(`lecture|tutorial|lab`).

### POST `lecturer/courses` · PUT `lecturer/courses/{course}` · DELETE `lecturer/courses/{course}`

Body (web rules): `code` ≤ 20, `title` ≤ 255, `description` ≤ 2000, `credit_hours` 1–20, `num_weeks`
1–52, `teaching_mode`, `format` (list of keys, as the detail returns it), `faculty_id`,
`programme_id`, `academic_term_id` (all checked against this institution). Create also takes
`clos` `[{code, description}]` and `topics` `[{week_number, title}]`, needs the `lecturer` or `admin`
role and makes me the owner. Update is **owner or admin only**, changes only the fields sent and
also takes `status` ∈ `draft|active|inactive|archived` (the web form offers status but ignores it).
Both → `{"message", "data": {course detail}}` (the `GET lecturer/courses/{course}` shape).
Delete is a soft delete, owner or admin only (the web lets any section lecturer delete).

### POST/DELETE `lecturer/courses/{course}/clos[/{clo}]` · `lecturer/courses/{course}/topics[/{topic}]`

Owner only. CLO body `{code ≤ 20, description ≤ 1000}`; topic body `{week_number 1…num_weeks, title ≤ 255}`.
→ `{"message", "data": {"id", ...}}`. Deletes are hard deletes, as on the web.

### POST `lecturer/courses/{course}/sections` · PUT `lecturer/courses/{course}/sections/{section}`

Body: `name` ≤ 50, `code` ≤ 20, `capacity` 1–500, `academic_term_id`, `lecturer_ids` (institution
staff). Create is owner only. Update is anyone with section access; only fields sent change, and
`lecturer_ids` (owner only) is applied only when sent — the web wipes co-lecturers when it is left
out. → `{"message", "data": {section}}` (the `sections[]` shape of the course detail).

### PUT `lecturer/courses/{course}/sections/{section}/schedule`

Body `{"schedule": [{day, start_time "HH:mm", end_time (after start), location?, type}]}` (≤ 10);
replaces the timetable, `[]` clears it. → `{"message", "data": {section}}`.

### POST `lecturer/courses/{course}/sections/{section}/students/import`

Multipart `csv_file` (csv/txt ≤ 2 MB). Columns by header, any order: name (`name|student_name|full_name`),
email (`email|student_email|e-mail`), optional ID (`student_id|id_number|matric|student_id_number`); a
UTF-8 BOM is fine. Unknown emails get an account (no email is sent — they use "Forgot password").
Previously removed students are re-enrolled.
→ `{"message": "2 students added. 1 skipped.", "data": {"imported": 1, "reactivated": 1, "skipped": 1, "errors": [{"line": 3, "message": "Missing name or email."}]}}`

---

## Course materials

Anyone with course access. Sections are the weekly headings students see.

### GET `lecturer/courses/{course}/materials`

`{"data": {"course": {...}, "sections": [{"id", "title", "is_visible", "sort_order", "items": [item]}]}}` —
every section, hidden and empty ones included. `item` is the student `MaterialItemResource` shape
(`id, type file|link|drive, title, description, file_type, size_bytes, size_label, created_at,
download_url, external_url`) plus `sort_order` and `material_section_id`.

### Sections

- POST `.../materials/sections` `{title}` → 201 section.
- PATCH `.../materials/sections/{section}` `{title?, is_visible?}` — hidden sections vanish for students (no web switch exists).
- DELETE `.../materials/sections/{section}` — deletes its items and their stored files.
- POST `.../materials/sections/{section}/move` `{direction: up|down}` → `data.order` (section ids).

### Items

- POST `.../materials/sections/{section}/files` — multipart `file` (≤ 25 MB), `title?`, `description?`.
  One file per request, stored on Lectura (the phone never pushes to Drive).
- POST `.../materials/sections/{section}/links` `{title, url, description?}`.
- PATCH `.../materials/items/{file}` `{title?, description?, url? (links only), material_section_id?}` — moving to another section of the same course is new on the phone.
- DELETE `.../materials/items/{file}` — also deletes the stored file (or the Drive copy via its uploader).

---

## Student groups

Group sets belong to a section. I see and change only the sets of my sections (web: any set of the course).

### GET `lecturer/courses/{course}/group-sets`

`data[]`: `{id, name, type lecture|lab|tutorial, description, creation_method, max_group_size, is_active, section {id,name}, groups_count, created_at}`; `meta.sections` = my sections (for the create form).

### POST `lecturer/courses/{course}/group-sets`

`{name, section_id (one of mine), type, description?, creation_method manual|random, group_size 2–20 (random)}` → 201 set detail.

### GET · PATCH · DELETE `lecturer/courses/{course}/group-sets/{set}`

Detail = the summary plus `groups[{id, name, color_tag, members[{id, name, student_id_number, role member|leader}]}]` (leader first), `unassigned[]` (enrolled students in no group) and `bound_count` (assessments/assignments using the set).
PATCH `{name?, description?, is_active?}`. DELETE is a soft delete. Every group action below answers with this detail.

### Groups and members

- POST `.../groups` `{name}`; PATCH `.../groups/{group}` `{name}` (rename is new).
- DELETE `.../groups/{group}` — 409 when it has members or submissions unless `{"confirm": true}`; deleting removes its workspace.
- POST `.../groups/{group}/members` `{user_id}` — must be enrolled in the set's section and in no other group of the set (422 otherwise).
- DELETE `.../groups/{group}/members/{user}`.
- POST `.../members/{user}/move` `{group_id}` — joins as a member (a leader loses the role).
- POST `.../groups/{group}/leader` `{user_id}` — one leader per group.
- POST `.../arrange-random` `{group_size 2–20, replace?}` — re-deals everyone; 409 when groups exist unless `replace: true` (the message says how many assessments/assignments use the set).

Still web-only: the Course Files archive (folders, tags), group swap approvals and the group score (which the web does not save).

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

### POST `lecturer/wheel/spins`

Shares a spin the app has just chosen, as it starts animating, so enrolled students can watch it (`student/wheel`)
and their phones are alerted once it lands. The web wheel posts the same thing to `/{tenant}/random-wheel/spins`.
```json
{ "session_id": 2, "candidate_ids": [3, 7, 9], "winner_id": 7, "turns": 6, "duration_ms": 5200 }
```
`candidate_ids` are the wheel's names in segment order; every one must be `present`/`late` in that session and
`winner_id` must be among them (422 otherwise). `turns` 1–20, `duration_ms` 1000–15000.
Response `{"message": "Spin shared with the class.", "data": {"id": 14}}`. 403 course access.

The push (`kind: random_wheel_pick`, data `spin_id`, `course_id`, `course_code`, `is_winner` `"1"|"0"`) is queued with a
delay of `duration_ms`, so it arrives as the wheel stops. It goes to every active student of the section; only the
picked student also gets a database notification. On Android it uses the app's `random_wheel` channel (high importance).

---

## Watch analytics (episodes)

Animated episodes are uploaded on the web (Materials → Episodes). These endpoints show how students
watch them. Every count covers only **my students**: active students of the course's sections I can
access (assigned sections + unassigned ones of courses I own; admins: all), the same rule as the
attendance index.

### GET `lecturer/courses/{course}/watch`

```json
{
  "data": {
    "series": { "id": 2, "title": "Titis: A Piping Story", "tagline": "…", "cover_url": "https://…signed…" },
    "students_count": 43,
    "episodes": [
      {
        "id": 7,
        "episode_number": 1,
        "title": "Titis Leaves Home",
        "source": "youtube",
        "week_number": 2,
        "status": "published",
        "is_available": true,
        "available_at": "2026-10-06T01:00:00+00:00",
        "required_by": "2026-10-14T00:00:00+00:00",
        "duration_seconds": 275,
        "poster_url": "https://…signed…",
        "started": 31,
        "finished": 24,
        "checks_count": 1,
        "first_try_correct_percent": 79
      }
    ]
  }
}
```

- `series` is `null` (and `episodes` empty) when the course has no series yet.
- Drafts are included (`status: "draft"`), so a lecturer can see what is not yet released.
- `status` ∈ `draft|locked|published`. A `locked` episode shows students "Coming soon" until a
  release time is set; with none set, `available_at` is `null`.
- `first_try_correct_percent`: share of first answers that were right across the episode's checks;
  `null` when nobody answered.

### GET `lecturer/watch/episodes/{episode}`

Query: `section_id` (optional) narrows every figure to one of my sections; without it the report
covers all of them. A section that isn't mine in this course → 422 on `section_id`.

```json
{
  "data": {
    "sections": [
      { "id": 1, "name": "Section 01", "students": 43 },
      { "id": 2, "name": "Section 02", "students": 38 }
    ],
    "section_id": null,
    "episode": { "…same fields as an episodes[] entry…": "" },
    "audience": {
      "students": 43,
      "started": 31,
      "finished": 24,
      "not_started": 12,
      "average_watched_percent": 71
    },
    "scenes": [
      { "code": "S01", "title": "Meet Titis", "start_seconds": 0, "reached": 31, "reached_percent": 100, "rewinds": 2 },
      { "code": "S06", "title": "Pipe = pressure-tight cylinder carrying fluid", "start_seconds": 104, "reached": 25, "reached_percent": 81, "rewinds": 71 }
    ],
    "most_rewound_scene": { "code": "S06", "title": "Pipe = pressure-tight cylinder carrying fluid", "rewinds_per_viewer": 2.3 },
    "checks": [
      {
        "id": 31,
        "at_seconds": 167,
        "prompt": "Which of these counts as piping under B31.3?",
        "answered": 29,
        "first_try_correct_percent": 79,
        "options": [
          { "id": 90, "label": "Building frame", "is_correct": false, "chosen": 3, "chosen_percent": 10 },
          { "id": 91, "label": "Pipe hanger", "is_correct": true, "chosen": 23, "chosen_percent": 79 },
          { "id": 92, "label": "Pump casing", "is_correct": false, "chosen": 3, "chosen_percent": 11 }
        ]
      }
    ],
    "students": [
      {
        "user_id": 4,
        "name": "Zul Hakim",
        "student_id_number": "A21EC0002",
        "section_name": "Section 02",
        "status": "not_started",
        "watched_percent": 0,
        "last_watched_at": null,
        "checks_correct": 0
      }
    ],
    "reminder": { "last_sent_at": null, "available_at": null }
  }
}
```

- `average_watched_percent`: mean of each starter's furthest point as a share of the duration.
- `scenes[].reached`: starters whose furthest point reached the scene's start; `reached_percent` is of
  starters. `rewinds`: backward jumps of 5 s or more that landed inside the scene.
- `most_rewound_scene` (or `null`): the scene with the most rewinds per starter, when at least one
  rewind was recorded.
- `options[].chosen`: students whose **latest** answer is that option (percent of `answered`).
- `sections`: my sections of the course by name, each with its active students, for the filter.
  `section_id` echoes the filter in use (`null` = all).
- `students`: not started first, then watching, then finished; by name within each.
  `section_name` labels the row only when no section is picked (`null` when filtered).
- `reminder.available_at`: when another reminder may be sent (`null` = now).

### POST `lecturer/watch/episodes/{episode}/remind`

Body `{ "audience": "not_started" | "not_finished", "section_id": 2 }` (`audience` defaults to
`not_started`; `section_id` optional, as in the report). Sends the `episode_reminder` notification
(in-app + push) to my students in that audience, limited to the section when one is given. The
one-per-hour limit is per episode, whichever section it went to.

```json
{ "message": "Reminder sent to 12 students.", "data": { "sent": 12, "last_sent_at": "2026-10-08T02:00:00+00:00", "available_at": "2026-10-08T03:00:00+00:00" } }
```

Errors (422 `{"message"}`): `This episode is not released yet.` · `Everyone in this group has already
started it.` (or `… finished it.`) · `A reminder went out at 10:00 AM. You can send another after
11:00 AM.` (one reminder per episode per hour, whoever sent it).

---

### GET `lecturer/courses/{course}/watch/preview`

"Preview as student": the series exactly as an enrolled student gets it from
`GET student/watch/series/{series}` (same shape, see `student.md` → Watch), for a lecturer of the
course who is not enrolled. Drafts are left out and locked or scheduled episodes come back closed
(`is_available: false`), as students see them. `progress` is the lecturer's own, so normally `null`.
404 `"Students can't see any episodes in this course yet."` when there is no series or every episode
is a draft.

### GET `lecturer/watch/episodes/{episode}/preview`

The student playback response (`GET student/watch/episodes/{episode}`) for any episode of the
course, drafts and locked ones included, plus `"preview": true` and `can_download: false`. Each check
also carries `correct_option_id` and `explanation`, so the app reveals the answer locally: in preview
the app posts no progress and no answers.

### PATCH `lecturer/watch/episodes/{episode}/release`

```json
{ "status": "locked", "publish_at": "2026-11-02T08:00:00+08:00", "notify_students": true }
```

- `status` ∈ `draft|locked|published` (required). `draft` hides it; `locked` shows "Coming soon"
  until `publish_at`; `published` is open now, or from `publish_at` when that is in the future.
- `publish_at`: ISO-8601 with offset, or `null` to clear it. `notify_students` is optional; when it
  is on, students get `episode_published` the moment the episode opens (now, or by the scheduler).
- Response: `{"message": "Episode 8 unlocks Mon 2 Nov, 8:00 AM.", "data": {…episode summary…}}`.
  The summary (also in the course list and analytics) now includes `publish_at` and
  `notify_students`. Messages: "… is a draft. Students can't see it.", "… is locked. Students see it
  as coming soon.", "… unlocks {date}.", "… is released." (+ " Students were notified.").

## Differences from the web app (intentional)

- Section endpoints (show, toggle, add/remove student) now check course **and** section access; the web SectionController had no authorization.
- Record override checks that the lecturer can access the session and that the record belongs to it (web did neither).
- Adding a previously removed student re-activates the enrolment instead of failing with "already enrolled".
- `end` on an ended session and `reopen` while another session is active for the section return 409 instead of silently proceeding.
- Token on an ended session returns 409 (web: 403 `{"error": "Session ended"}`).
- Excuse review admits the section lecturers who could already set the record to `excused` through the override (session access); the web lets only the course owner review. It also refuses to review an excuse twice.
- Assignment marking checks course access, that the submission belongs to the assignment and that marks stay within each criterion's and the assignment's maximum; the web's finalize route checks none of these.
- Assessment release, unrelease and marking only reach students in my sections (web release-all covers every section; manual entry accepts any user id). Unrelease retracts the whole group, mirroring release; the web retracts one row. `weighted_marks` always uses percentage × weightage.
