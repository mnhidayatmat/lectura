# Lectura Go API — Student Assessments & Group Workspace

All paths are relative to `/api/v1/t/{tenant}/` and require `Authorization: Bearer <token>` and `Accept: application/json`.
Route names are prefixed `api.v1.tenant.`.

Conventions (shared with the rest of the mobile API):

- Reads return `{"data": ...}`. Actions return `{"message": "...", "data": ...}`.
- Validation errors: `422 {"message": "...", "errors": {"field": ["..."]}}`. Business-rule failures that the web shows as form errors (e.g. "Cannot delete a graded submission.") are also returned as 422 on the named field.
- Permission failures: `403 {"message": "Human readable reason"}`. Records from another institution: `404`.
- Timestamps are ISO-8601 with offset (`2026-09-18T23:59:00+08:00`); plain dates are `YYYY-MM-DD`. Decimals are numbers.
- `download_url` values are absolute URLs to authenticated API endpoints — send the bearer token when fetching them. They stream the file with `Content-Disposition: attachment; filename="<original name>"`.

---

## Part 1 — Student assessments

Access rule for every assessment endpoint: the user must have an active enrollment in a section of `{course}`, and `{assessment}` must belong to `{course}` (otherwise `403`).

### GET `assessments`

Assessments that require submission (`status` active or completed) across all courses the student is enrolled in, grouped by course, ordered by due date.

```json
{
  "data": [
    {
      "course": { "id": 12, "code": "SKM3013", "title": "Fluid Mechanics" },
      "assessments": [
        {
          "id": 41,
          "course_id": 12,
          "title": "Lab Report 1",
          "type": "lab",
          "type_label": "Lab",
          "total_marks": 50,
          "weightage": 10,
          "due_date": "2026-09-18T23:59:00+08:00",
          "is_past_due": false,
          "status": "active",
          "is_group": false,
          "group": null,
          "submission_state": "not_submitted",
          "submission": null,
          "score": null
        },
        {
          "id": 44,
          "course_id": 12,
          "title": "Group Project",
          "type": "project",
          "type_label": "Project",
          "total_marks": 100,
          "weightage": 30,
          "due_date": "2026-10-02T17:00:00+08:00",
          "is_past_due": false,
          "status": "active",
          "is_group": true,
          "group": { "id": 7, "name": "Group A", "is_leader": true },
          "submission_state": "submitted",
          "submission": {
            "status": "submitted",
            "status_label": "Submitted",
            "is_late": false,
            "submitted_at": "2026-09-11T21:40:12+08:00"
          },
          "score": null
        },
        {
          "id": 39,
          "course_id": 12,
          "title": "Assignment 1",
          "type": "assignment",
          "type_label": "Assignment",
          "total_marks": 50,
          "weightage": 10,
          "due_date": "2026-09-01T23:59:00+08:00",
          "is_past_due": true,
          "status": "completed",
          "is_group": false,
          "group": null,
          "submission_state": "released",
          "submission": { "status": "graded", "status_label": "Graded", "is_late": false, "submitted_at": "2026-08-30T10:02:00+08:00" },
          "score": { "raw_marks": 40, "max_marks": 50, "percentage": 80 }
        }
      ]
    }
  ]
}
```

- `submission_state`: `released` (marks released — show `score`), `graded` (graded, marks not yet released), `submitted`, `overdue` (no submission and past due), `not_submitted`.
- `score` is only present when the lecturer has released marks. Colour hint used by the web: `percentage >= 70` green, `>= 40` amber, else red.
- Not paginated: bounded by the student's enrolled courses.

### GET `assessments/courses/{course}/{assessment}`

```json
{
  "data": {
    "id": 44,
    "title": "Group Project",
    "type": "project",
    "type_label": "Project",
    "description": "Design a pump test rig...",
    "total_marks": 100,
    "weightage": 30,
    "due_date": "2026-10-02T17:00:00+08:00",
    "is_past_due": false,
    "status": "active",
    "requires_submission": true,
    "is_group": true,
    "course": { "id": 12, "code": "SKM3013", "title": "Fluid Mechanics" },
    "instruction_file": {
      "name": "project-brief.pdf",
      "extension": "pdf",
      "download_url": "https://lectura.example/api/v1/t/utm/assessments/courses/12/44/instruction"
    },
    "submission_rules": {
      "accepted_extensions": ["pdf", "jpg", "jpeg", "png", "doc", "docx"],
      "max_file_size_kb": 25600,
      "max_notes_length": 1000,
      "multiple_files": true
    },
    "group": {
      "id": 7,
      "name": "Group A",
      "is_leader": false,
      "leader": { "id": 88, "name": "Hakim Rahman" },
      "vote_in_progress": false,
      "members": [
        { "id": 88, "name": "Hakim Rahman", "role": "leader" },
        { "id": 91, "name": "Nur Aina", "role": "member" }
      ]
    },
    "submission": {
      "id": 301,
      "status": "submitted",
      "status_label": "Submitted",
      "is_late": false,
      "is_group_submission": true,
      "submitted_at": "2026-09-11T21:40:12+08:00",
      "notes": "Final version attached.",
      "files": [
        {
          "id": 512,
          "name": "group-report.pdf",
          "mime_type": "application/pdf",
          "size_bytes": 204800,
          "is_graded_copy": false,
          "has_annotations": false,
          "download_url": "https://lectura.example/api/v1/t/utm/assessments/submission-files/512/download"
        }
      ]
    },
    "score": null,
    "can_submit": false,
    "can_resubmit": false,
    "can_delete": false,
    "notice": "Your submission is being reviewed. Marks will appear here once released."
  }
}
```

When marks are released, `score` is:

```json
{
  "raw_marks": 42.5,
  "max_marks": 50,
  "weighted_marks": 8.5,
  "percentage": 85,
  "feedback": "Strong analysis.",
  "released_at": "2026-09-20T09:00:00+08:00",
  "criteria": [
    { "id": 3, "title": "Analysis", "description": null, "max_marks": 30, "weightage": null, "marks": 27.5 },
    { "id": 4, "title": "Writing", "description": null, "max_marks": 20, "weightage": null, "marks": 15 }
  ]
}
```

(`criteria` is `[]` when the assessment has no rubric; a criterion's `marks` is `null` if it wasn't marked.)

Field notes:

- `instruction_file`: `null` when the lecturer attached none.
- `group`: `null` for individual assessments, or when the student isn't in a group for a group assessment (then `notice` explains).
- `submission`: the student's own row. For group assessments every member has a mirror row; `files` always shows the group's uploaded files (from whichever row holds them). `null` when nothing is submitted.
- `is_graded_copy`: the lecturer stamped a grade report onto the PDF; `download_url` then serves the graded copy.
- `has_annotations`: the lecturer drew on the file (annotated images aren't exposed by this API; link to the web if needed).
- Flags (same rules as the web page):
  - `can_submit` = no submission yet, `requires_submission`, status `active`, and (individual OR the student is the group leader).
  - `can_resubmit` / `can_delete` = a submission exists, it isn't `graded`, and (individual OR group leader).
- `notice` (display as an info banner, may be `null`), one of:
  - `Your submission is being reviewed. Marks will appear here once released.`
  - `This assessment is not currently accepting submissions.`
  - `You are not assigned to any group for this assessment. Contact your lecturer for group assignment.`
  - `Waiting for group leader to submit. {Name} will submit on behalf of your group.`
  - `Leader election in progress. Submission unlocks once voting closes.`
  - `No leader elected yet. Start a vote in your group workspace first.`
  - `The due date has passed. Your submission will be marked as late.`

Errors: `403 You are not enrolled in this course.` · `403 This assessment does not belong to this course.` · `404` for another institution's course/assessment.

### POST `assessments/courses/{course}/{assessment}/submit`

Multipart form:

| Field | Rules |
|---|---|
| `files[]` | required, at least 1; each `pdf,jpg,jpeg,png,doc,docx`, max 25600 KB |
| `notes` | optional string, max 1000 |

Behaviour mirrors the web: any existing ungraded submission of this student (or this group) is replaced; `is_late` is set when submitted after `due_date`; files are stored on the server and copied to the lecturer's Google Drive when connected; group submissions create mirror rows for every member; the lecturer is notified.

`201`:

```json
{
  "message": "Submission uploaded successfully.",
  "data": { "...": "same shape as GET assessments/courses/{course}/{assessment}" }
}
```

Messages: `Submission uploaded successfully.` · `Group submission uploaded successfully.` — each gets ` (Late submission)` appended when late.

Errors:
- `403 This assessment does not accept submissions.`
- `422 errors.files`: `You are not assigned to a group for this assessment.` · `Only the group leader can submit. Your group needs to hold a vote in the group workspace to elect one.` · `Cannot replace a graded submission.`
- `422 errors.files` / `errors["files.0"]` / `errors.notes`: validation (e.g. `The files.0 field must be a file of type: pdf, jpg, jpeg, png, doc, docx.`, `The files.1 field must not be greater than 25600 kilobytes.`)

### POST `assessments/courses/{course}/{assessment}/resubmit`

Replace the files of an existing submission. Same multipart fields and validation as submit. Old files are deleted.

`200` → `{"message": "Submission updated successfully.", "data": {detail}}` (` (Late submission)` appended when late).

Errors:
- `404 No submission found to replace.` (individual)
- `422 errors.files`: `Only the group leader can replace the submission.` · `No submission found to replace.` (group) · `Cannot modify a graded submission.`

### DELETE `assessments/courses/{course}/{assessment}/submission`

`200` → `{"message": "Submission deleted. You may resubmit if needed.", "data": {detail}}` (detail now has `submission: null`, `can_submit: true` if still open).

Group assessments delete every member's mirror row.

Errors: `404 No submission found.` · `422 errors.submission`: `Only the group leader can delete the submission.` · `Cannot delete a graded submission.`

### GET `assessments/courses/{course}/{assessment}/instruction`

Streams the instruction file (enrolled students). `404 Instruction file not found.`

### GET `assessments/submission-files/{file}/download`

Streams a submission file (the graded copy when one exists). Allowed for the submission's owner and members of the submitting group.

Errors: `403 You can only download your own submission files.` · `404 File not found.`

---

## Part 2 — Group workspace

Access rule: the user must be a member of `{group}` (`403 You are not a member of this group.`). A group belonging to another institution returns `404 Group not found.`

### GET `workspace`

The user's groups (all courses), sorted by course code.

```json
{
  "data": [
    {
      "id": 7,
      "name": "Group A",
      "color_tag": "#6366F1",
      "project_title": "Pump test rig",
      "member_count": 4,
      "my_role": "leader",
      "is_leader": true,
      "course": { "id": 12, "code": "SKM3013", "title": "Fluid Mechanics", "academic_term": "Semester 1 2026/2027" },
      "group_set": { "id": 3, "name": "Project Groups", "type": "lecture", "type_label": "Lecture" }
    }
  ]
}
```

`color_tag`, `project_title`, `academic_term` may be `null`. Not paginated (bounded by memberships).

### GET `workspace/{group}`

```json
{
  "data": {
    "id": 7,
    "name": "Group A",
    "color_tag": "#6366F1",
    "course": { "id": 12, "code": "SKM3013", "title": "Fluid Mechanics", "academic_term": "Semester 1 2026/2027" },
    "group_set": { "id": 3, "name": "Project Groups", "type": "lecture", "type_label": "Lecture" },
    "project": {
      "title": "Pump test rig",
      "description": "Design and test a centrifugal pump rig.",
      "deadline": "2026-10-02",
      "is_deadline_past": false,
      "whatsapp_link": "https://chat.whatsapp.com/abc"
    },
    "score": { "score": 85, "score_max": 100, "remarks": "Well done", "released_at": "2026-10-10T09:00:00+08:00" },
    "members": [
      { "id": 88, "name": "Hakim Rahman", "initial": "H", "avatar_url": null, "role": "leader", "is_me": false },
      { "id": 91, "name": "Nur Aina", "initial": "N", "avatar_url": "https://lh3.googleusercontent.com/...", "role": "member", "is_me": true }
    ],
    "my_role": "member",
    "is_leader": false,
    "vote_in_progress": false,
    "counts": { "members": 2, "messages": 34, "files": 5, "folders": 2, "tasks": 6, "tasks_done": 2, "tasks_overdue": 1 },
    "permissions": {
      "can_chat": true,
      "can_upload_files": true,
      "can_create_folders": true,
      "can_create_tasks": true,
      "can_delete_any_task": false,
      "can_delete_any_file": false
    },
    "uses_google_drive": false
  }
}
```

- `score` is `null` until the lecturer releases the group score.
- `members` lists the leader first. `id` is the user id.
- `uses_google_drive`: the current user's uploads go to their Google Drive (such files come back with `storage: "google_drive"` and an `external_url`).
- Project details, minutes, voting, swaps and sleeping-partner reports are web-only in this version.

### Chat

Message object:

```json
{
  "id": 1204,
  "body": "Meeting at 3pm at the lab",
  "user": { "id": 91, "name": "Nur Aina", "initial": "N", "avatar_url": null },
  "is_mine": true,
  "is_edited": false,
  "sent_at": "2026-09-11T15:02:41+08:00",
  "sent_at_label": "15:02"
}
```

#### GET `workspace/{group}/chat`

Query (all optional, use at most one):

| Param | Meaning |
|---|---|
| _(none)_ | newest 50 messages |
| `before_id` | up to 50 messages older than this id (scroll back) |
| `after_id` | up to 50 messages newer than this id (poll for new messages, e.g. every 5 s with the last id you have) |

Messages are always returned oldest → newest.

```json
{
  "data": [ { "id": 1203, "...": "message" }, { "id": 1204, "...": "message" } ],
  "meta": { "has_more": true, "limit": 50 }
}
```

`meta.has_more`: for newest/`before_id`, older messages exist; for `after_id`, more newer messages exist beyond this page (request again with the last id).

Real-time: the web also broadcasts `GroupMessageSent` on public channel `group.{groupId}.chat` (Reverb); polling with `after_id` is sufficient for mobile.

#### POST `workspace/{group}/chat`

Body: `{"body": "text"}` — required, max 2000.

`201` → `{"message": "Message sent.", "data": {message}}`

Errors: `422 errors.body`.

#### PATCH `workspace/{group}/chat/{message}`

Body: `{"body": "text"}` — required, max 2000. Own messages only.

`200` → `{"message": "Message updated.", "data": {message with "is_edited": true}}`

Errors: `403 You can only edit your own messages.` · `404` (deleted/unknown message) · `422 errors.body`.

#### DELETE `workspace/{group}/chat/{message}`

Own messages only. `200` → `{"message": "Message deleted.", "data": {"id": 1204, "deleted": true}}`

Errors: `403 You can only delete your own messages.` · `404`.

### Tasks

Task object:

```json
{
  "id": 55,
  "title": "Write introduction",
  "description": "Cover the pump theory and include references.",
  "status": "in_progress",
  "status_label": "In progress",
  "start_date": "2026-09-11",
  "due_date": "2026-09-14",
  "is_overdue": false,
  "assignee": { "id": 88, "name": "Hakim Rahman" },
  "creator": { "id": 91, "name": "Nur Aina" },
  "created_at": "2026-09-11T15:10:00+08:00",
  "can_edit": true,
  "can_delete": true
}
```

`status`: `todo` ("To do"), `in_progress` ("In progress"), `done` ("Done"). `is_overdue` = past `due_date` and not done. `description`, `assignee` and the dates may be `null`. `can_edit` / `can_delete` = creator or group leader.

Who may do what (same split as the web workspace):

| Action | Allowed for |
|---|---|
| Create a task | any group member |
| Change `status` | any group member |
| Change `title`, `description`, `assigned_to`, `start_date`, `due_date` | the task's creator or the group leader |
| Delete | the task's creator or the group leader |

#### GET `workspace/{group}/tasks`

Ordered by due date then creation. Not paginated.

```json
{
  "data": [ { "...": "task" } ],
  "meta": { "total": 6, "todo": 3, "in_progress": 1, "done": 2, "overdue": 1 }
}
```

#### POST `workspace/{group}/tasks`

| Field | Rules |
|---|---|
| `title` | required, max 200 |
| `description` | optional, max 2000 (trimmed; blank is stored as `null`) |
| `assigned_to` | optional user id of a group member |
| `start_date` | optional date `YYYY-MM-DD` |
| `due_date` | optional date, on/after `start_date` |

New tasks start as `todo`. `201` → `{"message": "Task added.", "data": {task}}`

Errors: `422` on `title`, `description`, `assigned_to` (`The selected assigned to is invalid.`), `due_date`.

#### PATCH `workspace/{group}/tasks/{task}`

Every field is optional; send only what changes. `null` clears `description`, `assigned_to`, `start_date` or `due_date`.

| Field | Rules |
|---|---|
| `status` | `todo` / `in_progress` / `done` — any group member |
| `title` | max 200, may not be empty — creator or leader |
| `description` | nullable, max 2000 — creator or leader |
| `assigned_to` | nullable user id of a group member — creator or leader |
| `start_date`, `due_date` | nullable dates `YYYY-MM-DD` — creator or leader |

Date order is checked against the values the task ends up with, so sending only `due_date` is still validated against the stored `start_date`.

`200` → `{"message": "Task updated.", "data": {task}}`

Errors:
- `403 You cannot manage this task.` (task of another group) · `404` (group of another institution)
- `403 Only the task creator or group leader can edit tasks.` (a member editing fields other than `status`)
- `422` on `status`, `title`, `description`, `assigned_to`, `due_date` (`The due date must be a date after or equal to start date.`)
- `422 errors.title` (`Send at least one field to update.`) when the body is empty

#### DELETE `workspace/{group}/tasks/{task}`

`200` → `{"message": "Task deleted.", "data": {"id": 55}}`

Errors: `403 Only the task creator or group leader can delete tasks.` · `403 You cannot manage this task.`

### Files & folders

File object:

```json
{
  "id": 210,
  "name": "proposal.pdf",
  "extension": "pdf",
  "size_bytes": 122880,
  "size_label": "120 KB",
  "description": "Draft proposal",
  "folder_id": null,
  "storage": "local",
  "uploaded_by": { "id": 91, "name": "Nur Aina" },
  "created_at": "2026-09-11T15:20:00+08:00",
  "download_url": "https://lectura.example/api/v1/t/utm/workspace/7/files/210/download",
  "external_url": null,
  "can_delete": true
}
```

- `storage`: `local` → fetch `download_url` with the bearer token. `google_drive` → `download_url` is `null`; open `external_url` (Google Drive web link) in the browser.
- `can_delete`: uploader or group leader.

Folder object:

```json
{
  "id": 14,
  "name": "Chapter 1",
  "file_count": 3,
  "is_synced_to_drive": false,
  "created_by": { "id": 91, "name": "Nur Aina" },
  "created_at": "2026-09-11T15:18:00+08:00",
  "can_delete": false
}
```

`can_delete` is `true` only when the folder is empty (any member may delete an empty folder).

#### GET `workspace/{group}/files`

Query: `folder_id` (optional). Without it: root folders plus files not in any folder. With it: that folder and its files (`folders` is empty). Files newest first; folders by name. Not paginated.

```json
{
  "data": {
    "folder": null,
    "folders": [ { "...": "folder" } ],
    "files": [ { "...": "file" } ]
  }
}
```

Errors: `404 Folder not found.` (folder not in this group).

#### POST `workspace/{group}/files`

Multipart:

| Field | Rules |
|---|---|
| `file` | required; `pdf,jpg,jpeg,png,doc,docx,xls,xlsx,pptx` (config `lectura.uploads.allowed_types`); max 25,000 KB (`lectura.uploads.max_file_size_mb` × 1000 KB, so just under 25 MiB) |
| `folder_id` | optional folder id of this group |
| `description` | optional, max 500 |

Stored in the uploader's Google Drive (`Lectura/Workspace/{Group}/{Folder}`) when they have Drive connected, otherwise on the server.

`201` → `{"message": "File uploaded.", "data": {file}}` (message is `File uploaded to Google Drive.` for Drive uploads)

Errors: `404 Folder not found.` · `403 This folder belongs to another group.` · `422 errors.file` (type/size, or `Google Drive upload failed: <reason>. Please check your Drive connection.`).

#### GET `workspace/{group}/files/{file}/download`

- Local file → streams the file.
- Drive file → `200 {"data": {"external_url": "https://drive.google.com/file/d/.../view"}}`

Errors: `403 You cannot access this file.` · `404 File not found.` · `404 Drive link not available for this file.`

#### DELETE `workspace/{group}/files/{file}`

Removes the file from Drive (via the uploader's account, when still connected) or server disk.

`200` → `{"message": "File deleted.", "data": {"id": 210}}`

Errors: `403 Only the uploader or group leader can delete files.` · `403 You cannot access this file.`

#### POST `workspace/{group}/folders`

Body: `{"name": "Chapter 1"}` — required, max 100.

`201` → `{"message": "Folder created.", "data": {folder}}`

Errors: `422 errors.name`.

#### DELETE `workspace/{group}/folders/{folder}`

`200` → `{"message": "Folder deleted.", "data": {"id": 14}}`

Errors: `403 You cannot manage this folder.` · `422 errors.folder`: `Delete all files in this folder first.`
