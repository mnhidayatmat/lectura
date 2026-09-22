# Lectura Go API — Student core + notifications

All paths are relative to `/api/v1/t/{tenant}/` (tenant slug), except the Devices and Account
sections, which sit directly under `/api/v1/`. Every request needs
`Authorization: Bearer <token>` and `Accept: application/json`. A multi-role user may send
`X-Lectura-Role: student`.

Conventions

- Reads: `{"data": ...}`. Actions: `{"message": "...", "data": {...}}`.
- Validation errors: HTTP 422 `{"message": "...", "errors": {"field": ["..."]}}`.
- Business-rule failures that are not field validation (check-in, excuse rules): HTTP 422 `{"message": "..."}` (no `errors`).
- Permission failures: 403 `{"message": "..."}`. Records from another institution, or unknown ids: 404.
- Timestamps are ISO-8601 with offset. The server runs in UTC (`config/app.php`), so it emits
  `+00:00`; examples below show `+08:00` for readability, and any offset parses the same. Decimals are JSON numbers
  (`90` or `92.5`). Nullable fields are always present with `null`.
- `course` fragment used everywhere: `{"id": 12, "code": "SKMM1203", "title": "Statics"}`.

---

## Dashboard

### GET `student/dashboard`

Upcoming deadlines: published assignments with a future deadline, plus assessments that require a
submission (status `active`/`completed`) with a future `due_date`, merged, sorted by `due_at`, max 5.
Recent marks: finalised assignment marks + released assessment scores, newest first, max 3.
`courses` is capped at 5 (`courses_count` is the total).

```json
{
  "data": {
    "courses_count": 3,
    "courses": [
      { "id": 12, "code": "SKMM1203", "title": "Statics", "lecturer_name": "Dr Hidayat" }
    ],
    "active_attendance_sessions": [
      {
        "id": 88,
        "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
        "section_name": "Section 01",
        "session_type": "lecture",
        "week_number": 3,
        "started_at": "2026-09-11T08:02:11+08:00",
        "my_status": null
      }
    ],
    "upcoming_deadlines": [
      {
        "kind": "assignment",
        "id": 41,
        "title": "Problem Set 1",
        "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
        "due_at": "2026-09-12T23:59:00+08:00",
        "submitted": false
      },
      {
        "kind": "assessment",
        "id": 7,
        "title": "Project Report",
        "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
        "due_at": "2026-09-14T17:00:00+08:00",
        "submitted": true
      }
    ],
    "recent_marks": [
      {
        "kind": "assignment",
        "id": 301,
        "title": "Quiz 1",
        "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
        "raw_marks": 18,
        "max_marks": 20,
        "percentage": 90,
        "grade": "A",
        "released_at": "2026-09-10T15:20:00+08:00"
      }
    ],
    "unread_notifications_count": 2
  }
}
```

- `my_status`: `null` (not checked in yet) or `present` | `late` | `absent` | `excused`.
- `recent_marks[].kind = "assignment"` → `id` is a mark id (open `student/marks/{id}`);
  `kind = "assessment"` → `id` is an assessment score id (shown in `student/marks` → `assessment_scores`), `grade` is always `null`.
- `upcoming_deadlines[].kind = "assessment"` → `id` is the assessment id (course id is in `course.id`).

---

## Courses

### GET `student/courses`

Active enrollments in this institution, one entry per course, sorted by code. Not paginated.

```json
{
  "data": [
    {
      "id": 12,
      "code": "SKMM1203",
      "title": "Statics",
      "status": "active",
      "credit_hours": 3,
      "lecturer_name": "Dr Hidayat",
      "sections": [ { "id": 31, "name": "Section 01", "code": "01" } ],
      "enrolled_at": "2026-09-01T10:00:00+08:00"
    }
  ]
}
```

### POST `student/courses/enroll`

Body: `{"invite_code": "ABCD-1234"}` (required, string, max 20). The code is upper-cased and all
non `A-Z0-9` characters are stripped before lookup (same as the web).

200 — new enrollment or reactivated enrollment:

```json
{
  "message": "Successfully enrolled in SKMM2323 — Section 05!",
  "data": {
    "course": { "id": 14, "code": "SKMM2323", "title": "Dynamics" },
    "section": { "id": 40, "name": "Section 05", "code": "05" },
    "already_enrolled": false
  }
}
```

200 — already enrolled: `message` = `"You are already enrolled in SKMM1203 — Section 01."`, `already_enrolled: true`.

422 `errors.invite_code` (one of):
- `That code belongs to a course, not a section. Please ask your lecturer for the section invite code (shown as "Student code" next to each section).`
- `Invalid invite code. Please check with your lecturer.`
- `This section is not accepting enrollments. Please contact your lecturer.`
- `This section is full. Please contact your lecturer.`
- `The invite code field is required.`

### GET `student/courses/{course}`

403 `You are not enrolled in this course.` · 404 unknown / other institution.

```json
{
  "data": {
    "course": {
      "id": 12,
      "code": "SKMM1203",
      "title": "Statics",
      "description": "Forces, moments and equilibrium.",
      "credit_hours": 3,
      "num_weeks": 14,
      "teaching_mode": "face_to_face",
      "status": "active",
      "lecturer": { "id": 5, "name": "Dr Hidayat", "avatar_url": null }
    },
    "my_sections": [
      {
        "id": 31,
        "name": "Section 01",
        "code": "01",
        "schedule": [
          { "day": "monday", "start_time": "08:00", "end_time": "10:00", "location": "BK1", "type": "lecture" }
        ],
        "lecturers": [ { "id": 5, "name": "Dr Hidayat" } ]
      }
    ],
    "attendance_summary": { "present": 8, "late": 1, "absent": 1, "total": 10, "rate": 90 },
    "upcoming_assignments": [
      { "id": 41, "title": "Problem Set 1", "type": "individual", "total_marks": 20, "deadline": "2026-09-12T23:59:00+08:00" }
    ],
    "topics": [ { "week_number": 1, "title": "Introduction to vectors" } ],
    "learning_outcomes": [ { "code": "CLO1", "description": "Analyse force systems in equilibrium." } ],
    "active_learning_plans": [
      { "id": 9, "title": "Free-body diagram clinic", "week_number": 3, "duration_minutes": 90, "activities_count": 4, "active_session_id": null }
    ],
    "counts": { "materials": 12, "upcoming_assignments": 1, "active_learning_plans": 1 }
  }
}
```

- `schedule[].day`: `monday`…`sunday`; `type`: `lecture` | `tutorial` | `lab` | `other`; times are `HH:mm` (24h).
- `attendance_summary.rate` is an integer percentage `(present + late) / total` (0 when `total` = 0).
- `upcoming_assignments`: published, deadline in the future or no deadline (`deadline: null`), max 5.
- `active_learning_plans[].active_session_id`: id of a live active-learning session to join, else `null`.
- `counts.materials`: files/links in visible material sections.

---

## Attendance

### POST `student/attendance/check-in`

Body: `{"payload": "<raw QR text>"}` (the scanned string, unchanged).

Optional `scanned_at` (ISO-8601 **with offset**, e.g. UTC `Z`) marks a scan queued while offline: it is
judged against the session's start/end window instead of "is the session running now", and the QR token
is verified at that instant. The server parses an offset-less time as UTC, so always send one.

200 — checked in:

```json
{
  "message": "Checked in successfully!",
  "data": {
    "status": "present",
    "checked_in_at": "2026-09-11T08:05:40+08:00",
    "session": {
      "id": 88,
      "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
      "section_name": "Section 01"
    }
  }
}
```

- Late (more than the session's late threshold after start): `message` = `"Checked in (late)."`, `status` = `"late"`.
- Already checked in: HTTP **200**, `message` = `"You have already checked in."`, `data` holds the existing record (`checked_in_at` may be `null` if the lecturer set the status manually).
  When that record is `absent` (set by the lecturer) the message is `"Your lecturer has marked you absent for this session."`;
  when `excused`, `"You are excused from this session."`. Check `data.status`, not the HTTP code, before showing success.

422 `{"message": ...}` (no `errors`):
- `Invalid QR code.`
- `This attendance session has ended.`
- `QR code has expired. Please scan the latest code.`
- `You are not enrolled in this section.`
- `This check-in is too old to submit. Ask your lecturer to mark you manually.` (queued scan in the future or past the offline grace window)
- `That scan was taken outside this session.` (queued scan)

422 validation: `errors.payload` when missing.

### GET `student/attendance`

One entry per enrolled course. Not paginated.

```json
{
  "data": [
    {
      "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
      "lecturer_name": "Dr Hidayat",
      "sections": ["Section 01"],
      "summary": {
        "present": 8,
        "late": 1,
        "absent": 2,
        "excused": 1,
        "total_sessions": 12,
        "absence_count": 2,
        "attendance_rate": 83.3,
        "warning": { "level": 1, "label": "First warning" }
      }
    }
  ]
}
```

- `attendance_rate` (float, one decimal) = `(total_sessions − absence_count) / total_sessions`; `100` when no ended sessions.
- `absence_count` counts late as absent when the course policy says so.
- `warning`: `null`, or the highest warning level issued; `label` comes from the course policy threshold, else `"Attendance Warning Level {n}"`. Suggested colours: level ≥ 3 red, 2 amber, 1 yellow.
- Rate colours used by the web: ≥ 80 emerald, ≥ 60 amber, else red.

### GET `student/attendance/courses/{course}`

403 `You are not enrolled in this course.`

```json
{
  "data": {
    "course": { "id": 12, "code": "SKMM1203", "title": "Statics", "lecturer_name": "Dr Hidayat" },
    "summary": { "present": 8, "late": 1, "absent": 2, "excused": 1, "total_sessions": 12, "absence_count": 2, "attendance_rate": 83.3, "warning": null },
    "policy": { "mode": "percentage", "bar_threshold": 20, "include_late_as_absent": false },
    "sessions": [
      {
        "id": 90,
        "week_number": 4,
        "session_type": "lecture",
        "started_at": "2026-09-08T08:00:00+08:00",
        "section_name": "Section 01",
        "status": "absent",
        "record": {
          "id": 1502,
          "checked_in_at": null,
          "can_submit_excuse": false,
          "excuse": {
            "id": 77,
            "status": "pending",
            "category": "medical",
            "category_label": "Medical",
            "reason": "Fever, clinic visit",
            "attachment_filename": "mc.pdf",
            "reviewer_note": null,
            "reviewed_at": null,
            "submitted_at": "2026-09-08T12:10:00+08:00"
          }
        }
      },
      {
        "id": 86,
        "week_number": 3,
        "session_type": "tutorial",
        "started_at": "2026-09-01T10:00:00+08:00",
        "section_name": "Section 01",
        "status": "no_record",
        "record": null
      }
    ]
  }
}
```

- Only ended sessions, newest first. `policy` is `null` when the course has no attendance policy.
- `status`: `present` | `late` | `absent` | `excused` | `no_record`.
- `session_type`: `lecture` | `tutorial` | `lab` | `extra` | `replacement`.
- `excuse.status`: `pending` | `approved` | `rejected`.
- `can_submit_excuse` is true only for an `absent` record without an excuse.

### POST `student/attendance/records/{record}/excuse`

`multipart/form-data`:
- `reason` — required, string, max 2000
- `category` — required: `medical` | `family_emergency` | `academic_conflict` | `official_duty` | `other`
- `attachment` — optional file, max 5 MB, `pdf, jpg, jpeg, png, doc, docx`

201:

```json
{
  "message": "Your excuse has been submitted for review.",
  "data": {
    "excuse": {
      "id": 77,
      "status": "pending",
      "category": "medical",
      "category_label": "Medical",
      "reason": "Fever, clinic visit",
      "attachment_filename": "mc.pdf",
      "reviewer_note": null,
      "reviewed_at": null,
      "submitted_at": "2026-09-08T12:10:00+08:00"
    }
  }
}
```

Errors: 403 `This attendance record does not belong to you.` · 404 record of another institution ·
422 `{"message": "You can only submit excuses for absent records."}` ·
422 `{"message": "An excuse has already been submitted for this session."}` ·
422 validation on `reason` / `category` / `attachment`.

---

## Course materials

### GET `student/materials`

```json
{
  "data": [
    { "id": 12, "code": "SKMM1203", "title": "Statics", "lecturer_name": "Dr Hidayat", "materials_count": 12 }
  ]
}
```

`materials_count` counts items in visible sections only — the same set `materials/courses/{course}`
lists and `counts.materials` on the course detail reports.

### GET `student/materials/courses/{course}`

403 `You are not enrolled in this course.` Only visible sections that contain at least one item,
in lecturer order; items in lecturer order.

```json
{
  "data": {
    "course": { "id": 12, "code": "SKMM1203", "title": "Statics", "lecturer_name": "Dr Hidayat" },
    "sections": [
      {
        "id": 3,
        "title": "Week 1 — Vectors",
        "description": null,
        "items_count": 3,
        "items": [
          {
            "id": 55,
            "type": "file",
            "title": "Lecture 1.pdf",
            "description": "Slides",
            "file_type": "application/pdf",
            "size_bytes": 204800,
            "size_label": "200 KB",
            "created_at": "2026-09-01T09:00:00+08:00",
            "download_url": "https://lectura.example/api/v1/t/utm/student/materials/courses/12/files/55/download",
            "external_url": null
          },
          {
            "id": 56,
            "type": "link",
            "title": "Intro video",
            "description": null,
            "file_type": null,
            "size_bytes": null,
            "size_label": null,
            "created_at": "2026-09-01T09:05:00+08:00",
            "download_url": null,
            "external_url": "https://youtu.be/abc"
          },
          {
            "id": 57,
            "type": "drive",
            "title": "Tutorial 1.docx",
            "description": null,
            "file_type": "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "size_bytes": 18321,
            "size_label": "17.89 KB",
            "created_at": "2026-09-02T11:00:00+08:00",
            "download_url": null,
            "external_url": "https://drive.google.com/file/d/xyz/view"
          }
        ]
      }
    ]
  }
}
```

- `type`: `file` (stored on the server → use `download_url` with the bearer token) | `link` | `drive` (open `external_url` in the browser).

### GET `student/materials/courses/{course}/files/{file}/download`

Streams the file (`Content-Disposition: attachment; filename="<title>"`). Allowed for enrolled
students and the course's lecturers/admins. `drive` items redirect (302) to the Drive URL.
403 `You do not have access to this file.` · 404 link items, files of another course, missing files.

---

## Marks & feedback

### GET `student/marks?filter=all|graded|pending`

`filter` defaults to `all` (422 `errors.filter` if invalid). Covers published/closed/marking/completed
assignments of enrolled courses, plus released assessment scores. Not paginated.

- `graded` → assignments whose mark is final; `pending` → the rest, and `assessment_scores` is `[]`.
- `stats` always describe all assignments regardless of the filter.

```json
{
  "data": {
    "filter": "all",
    "stats": { "total_assignments": 4, "graded_count": 2, "pending_count": 2, "average_percentage": 81.5 },
    "assignments": [
      {
        "assignment": { "id": 41, "title": "Lab Report", "type": "individual", "total_marks": 20, "deadline": "2026-09-05T23:59:00+08:00" },
        "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
        "status": "graded",
        "is_late": false,
        "submitted_at": "2026-09-05T20:11:00+08:00",
        "mark": { "id": 301, "total_marks": 18, "max_marks": 20, "percentage": 90, "grade": "A", "finalized_at": "2026-09-10T15:20:00+08:00" },
        "feedback": {
          "performance_level": "advanced",
          "ai_generated": true,
          "strengths": "Clear method",
          "improvement_tips": "Label units consistently.",
          "missing_points": null,
          "misconceptions": null,
          "revision_advice": "Revisit moment arms.",
          "released_at": "2026-09-10T15:20:00+08:00"
        }
      },
      {
        "assignment": { "id": 42, "title": "Problem Set 2", "type": "group", "total_marks": 30, "deadline": null },
        "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
        "status": "not_submitted",
        "is_late": false,
        "submitted_at": null,
        "mark": null,
        "feedback": null
      }
    ],
    "assessment_scores": [
      {
        "id": 900,
        "assessment": { "id": 7, "title": "Mid-term Test", "type": "test", "weightage": 20 },
        "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
        "raw_marks": 40,
        "max_marks": 50,
        "percentage": 80,
        "feedback": "Good work",
        "released_at": "2026-09-09T10:00:00+08:00",
        "answer_script": {
          "filename": "Mid-term script.pdf",
          "download_url": "https://lectura.example/api/v1/t/utm/student/marks/assessment-scores/900/answer-script",
          "external_url": null
        }
      }
    ]
  }
}
```

- `status`: `graded` | `submitted` | `not_submitted`. `mark` is only present when graded; `feedback` only when released.
- `performance_level`: `low` | `average` | `advanced` (colours: advanced emerald, average amber, low red). Percentage colours: ≥ 70 emerald, ≥ 40 amber, else red.
- `assessment.type`: `quiz` | `assignment` | `test` | `project` | `presentation` | `lab` | `final_exam` | `other`.
- `answer_script`: `null` when none; otherwise exactly one of `download_url` (bearer-token file) / `external_url` (Google Drive) is set.

### GET `student/marks/{mark}`

403 `These marks do not belong to you.` · 403 `These marks have not been released yet.` (mark not final) · 404 other institution.

```json
{
  "data": {
    "mark": { "id": 301, "total_marks": 18, "max_marks": 20, "percentage": 90, "grade": "A", "finalized_at": "2026-09-10T15:20:00+08:00" },
    "assignment": { "id": 41, "title": "Lab Report", "type": "individual", "total_marks": 20, "deadline": "2026-09-05T23:59:00+08:00" },
    "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
    "submission": {
      "id": 610,
      "submitted_at": "2026-09-05T20:11:00+08:00",
      "is_late": false,
      "files": [ { "id": 1201, "file_name": "report.pdf", "file_type": "application/pdf", "size_bytes": 523311 } ]
    },
    "feedback": {
      "performance_level": "advanced",
      "ai_generated": false,
      "strengths": "Clear method",
      "improvement_tips": null,
      "missing_points": null,
      "misconceptions": null,
      "revision_advice": null,
      "released_at": "2026-09-10T15:20:00+08:00"
    }
  }
}
```

`submission` and `feedback` may be `null`.

### GET `student/marks/assessment-scores/{score}/answer-script`

Streams the released answer script (`filename` from `answer_script.filename`, default `answer-script.pdf`);
Drive-stored scripts redirect (302). 404 when not the student's score, not released, no script, or file missing.

---

## Assignments

The list shows **published** assignments of courses the student is enrolled in. Detail and downloads
also open closed/marking/completed ones (the marks list links to them), with `can_submit: false` and
`blocked_reason: "This assignment is no longer accepting submissions."`; submitting one returns 422 with
that message. A draft, or an assignment of another institution, returns 404. Not enrolled → 403
`You are not enrolled in this course.`

### GET `student/assignments`

Top-level assignments only (parts of a multi-part assignment are listed inside the parent), ordered by
course code then newest deadline. Not paginated.

```json
{
  "data": [
    {
      "id": 41,
      "title": "Problem Set 1",
      "type": "individual",
      "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
      "deadline": "2026-09-12T23:59:00+08:00",
      "is_past_due": false,
      "total_marks": 20,
      "submission_type": "file",
      "status": "not_submitted",
      "submitted_at": null,
      "is_late": false,
      "mark": null
    }
  ]
}
```

- `status`: `graded` | `submitted` | `overdue` (deadline passed, nothing submitted) | `not_submitted`.
- `type`: `individual` | `group`. `submission_type`: `file` | `text` | `both`.
- `mark` (same shape as `student/marks`) is present only once the mark is finalised.

### GET `student/assignments/{assignment}`

```json
{
  "data": {
    "assignment": {
      "id": 41,
      "title": "Problem Set 1",
      "type": "group",
      "course": { "id": 12, "code": "SKMM1203", "title": "Statics" },
      "deadline": "2026-09-12T23:59:00+08:00",
      "is_past_due": false,
      "total_marks": 20,
      "submission_type": "both",
      "description": "Solve every question and show your working.",
      "instruction": {
        "filename": "brief.pdf",
        "download_url": "https://lectura.example/api/v1/t/utm/student/assignments/41/instruction",
        "external_url": null
      },
      "sub_assignments": [
        { "id": 42, "title": "Part A", "deadline": null, "total_marks": 10 }
      ]
      // Always `[]` unless the installation has the `assignments.parent_id` column —
      // the models and the web UI use it, but no migration creates it.
    },
    "rules": {
      "submission_type": "both",
      "allows_files": true,
      "allows_text": true,
      "requires_files": false,
      "requires_text": false,
      "accepted_extensions": ["pdf", "jpg", "jpeg", "png", "doc", "docx"],
      "max_file_size_bytes": 26214400,
      "max_notes_length": 1000,
      "allow_resubmission": true,
      "max_resubmissions": 2,
      "attempts_used": 1,
      "attempts_remaining": 2
    },
    "group": {
      "id": 8,
      "name": "Group A",
      "kind": "assignment_group",
      "is_leader": false,
      "leader_name": "Lead Student",
      "members": [ { "id": 5, "name": "Lead Student", "is_leader": true } ]
    },
    "submission": {
      "id": 610,
      "submitted_at": "2026-09-11T20:11:00+08:00",
      "is_late": false,
      "status": "submitted",
      "submission_number": 1,
      "notes": "Sorry for the formatting.",
      "text_content": null,
      "is_mine": true,
      "submitted_by": "Nur Aina",
      "files": [
        {
          "id": 1201,
          "file_name": "report.pdf",
          "file_type": "application/pdf",
          "size_bytes": 523311,
          "download_url": "https://lectura.example/api/v1/t/utm/student/assignments/41/files/1201/download",
          "annotated_url": null
        }
      ]
    },
    "status": "submitted",
    "mark": null,
    "feedback": null,
    "can_submit": false,
    "can_resubmit": false,
    "blocked_reason": "Only the group leader can submit for the group."
  }
}
```

- `group` is `null` for individual assignments. `kind`: `assignment_group` (per-assignment groups) or
  `student_group_set` (course group set, where the leader is elected by the group's vote).
- `submission` is the student's own submission, or the group's when they have none of their own
  (`is_mine` says which). `annotated_url` appears only once feedback is released and the lecturer
  annotated that file.
- `mark` / `feedback` appear only when the mark is finalised / the feedback released.
- `blocked_reason` explains why `can_submit` is false: not in a group, not the group leader, or
  already submitted.
- `attempts_remaining`: `0` when resubmission is off, `null` when it is on with no configured limit.

### POST `student/assignments/{assignment}/submit`

`multipart/form-data`, matching `submission_type`:
- `files[]` — required for `file`, optional for `both`; each ≤ 25 MB, `pdf, jpg, jpeg, png, doc, docx`
- `text_content` — required for `text`, optional for `both`
- `notes` — optional, max 1000 characters

201:

```json
{
  "message": "Submission uploaded successfully.",
  "data": { "submission": { "id": 611, "submitted_at": "…", "is_late": false, "submission_number": 1, "files": [] } }
}
```

- Past the deadline the message ends with ` (Late submission)` and `is_late` is true.
- Group assignments: only the leader may submit, the message is `Group submission uploaded successfully.`,
  and the submission is mirrored onto every member (as on the web).
- 422 `{"message": ...}` for: `You have already submitted this assignment.`,
  `Only the group leader can submit for the group.`,
  `Only the group leader can submit. Your group needs to hold a vote to elect a leader.`,
  `You are not assigned to a group for this assignment.`
- 422 validation errors on `files`, `files.*`, `text_content`, `notes`; for `both` with neither
  provided: `errors.files` = `Please provide either files or text content.`

### GET `student/assignments/{assignment}/instruction`

Streams the instruction file, or redirects (302) to Google Drive when the lecturer stored it there. 404 when there is none.

### GET `student/assignments/{assignment}/files/{file}/download`

Streams one of the student's own (or their group's) submission files. 403 for anyone else's file, 404 when the file is missing.

### GET `student/assignments/{assignment}/files/{file}/annotated`

Streams the lecturer's annotated copy of that file (inline image), when one exists.

---

## Notifications (any role)

### GET `notifications?page=1`

Paginated, 20 per page, newest first. Notifications are per user (not per institution).

```json
{
  "data": [
    {
      "id": "9f0c3a52-7c1e-4b55-9d8e-2d6f0a1b3c4d",
      "type": "AttendanceWarningNotification",
      "kind": "attendance_warning",
      "title": "Attendance Warning 2",
      "body": "You have reached 25% absence (4 sessions) in SKMM1203 Statics.",
      "icon": "warning",
      "color": "amber",
      "related": { "course_id": 12, "course_code": "SKMM1203", "level": 2 },
      "read_at": null,
      "created_at": "2026-09-11T09:00:00+08:00"
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=1", "prev": null, "next": null },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "…/notifications",
    "per_page": 20,
    "to": 1,
    "total": 1,
    "unread_count": 1
  }
}
```

- `id` is a UUID string.
- `kind`: `assignment_published` | `feedback_released` | `assessment_marks_released` | `submission_received` | `assessment_submission_received` | `attendance_alert` | `attendance_warning` (may be `null` for unknown types).
- `icon`: `document` | `chart` | `upload` | `alert` | `warning`. `color`: `amber` | `emerald` | `indigo` | `red` | `teal` | `yellow` | `slate`.
- `related` is an object (possibly `{}`) with any of `assignment_id`, `assessment_id`, `course_id`, `course_code`, `level`.

### GET `notifications/unread-count`

```json
{ "data": { "count": 2 } }
```

### POST `notifications/{id}/read`

```json
{
  "message": "Notification marked as read.",
  "data": { "id": "9f0c3a52-7c1e-4b55-9d8e-2d6f0a1b3c4d", "read_at": "2026-09-11T09:05:00+08:00", "unread_count": 1 }
}
```

404 when the id is not one of the user's notifications.

### POST `notifications/read-all`

```json
{ "message": "All notifications marked as read.", "data": { "unread_count": 0 } }
```

## Push notifications (Firebase Cloud Messaging)

Not tenant-scoped: these sit at `/api/v1/devices`, beside `/me`.

### POST `/api/v1/devices`

Body `{ "token": "<FCM registration token>", "platform": "android" | "ios" }`. The app calls it after
sign-in and whenever Firebase rotates the token. A token belongs to one install, so registering it
again moves it to the signed-in user.

```json
{ "message": "Device registered.", "data": { "token": "…" } }
```

The token is tied to the Sanctum token of the request: `POST /auth/logout` and account deletion
remove it, so a signed-out phone stops receiving pushes without a separate call.

### DELETE `/api/v1/devices`

Body `{ "token": "…" }`. Removes it if it is the user's own; always 200.

### What is pushed

Only the lecturer-facing notifications: `submission_received`, `assessment_submission_received` and
`attendance_alert`. Title and body are the stored notification's `title` / `body`; the data payload
carries string values only:

```json
{ "notification_id": "9d3f…", "kind": "submission_received", "assignment_id": "12" }
```

`notification_id` is the id used by `notifications/{id}/read`; the related keys are the same ones as
`related` above (`assignment_id`, `assessment_id`, `course_id`, `course_code`, `level`) when present.
Tokens FCM reports as `UNREGISTERED` are deleted. Without `FCM_CREDENTIALS_PATH` (a Firebase
service-account JSON) nothing is pushed and notifications behave as before.
