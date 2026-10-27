# Lectura Go API — Live participation (student)

Student side of **live quizzes**, **offline (self-paced) quizzes** and **live active-learning sessions**.

- Base path: `/api/v1/t/{tenant}/` — every path below is relative to it (e.g. `live/hub` → `/api/v1/t/utm/live/hub`).
- Auth: `Authorization: Bearer <token>`, `Accept: application/json`. Optional `X-Lectura-Role: student`.
- Timestamps are ISO-8601 (`2026-09-11T14:29:33+00:00`); ids are ints; scores/points are numbers (may be `2` or `2.5`).
- Every polled/bootstrap payload has `server_time` — use it (not the device clock) to run countdowns.
- Errors:
  - `401` unauthenticated · `403 {"message": "..."}` not allowed · `404` not found (includes records of another institution) · `422` either `{"message": "..."}` (state errors) or Laravel validation `{"message", "errors": {field: [msg]}}`.
  - With `APP_DEBUG=true` servers, `403/404/422` state errors also contain `exception/file/line/trace` — ignore them; only read `message` / `errors`.

Routes (names prefixed `api.v1.tenant.live.`):

| Method | Path | Purpose |
|---|---|---|
| GET | `live/hub` | What the student can join now + recent history |
| POST | `live/join` | Resolve a 6-char join code to a session or quiz |
| GET | `live/quizzes/{quiz}` | Quiz bootstrap (registers participant when playable) |
| GET | `live/quizzes/{quiz}/state` | Live quiz state — poll every ~2 s |
| POST | `live/quizzes/{quiz}/nickname` | Set the name shown on the board (lobby) |
| POST | `live/quizzes/{quiz}/respond` | Answer the open live question |
| POST | `live/quizzes/{quiz}/submit-offline` | Submit all answers of an offline quiz |
| GET | `live/quizzes/{quiz}/result` | Personal result with correct answers |
| GET | `live/sessions/{session}` | Active-learning session bootstrap (joins when active) |
| GET | `live/sessions/{session}/state` | Current activity — poll every ~3–5 s |
| POST | `live/sessions/{session}/respond` | Submit/update response to the current activity |
| GET | `live/sessions/{session}/review` | Read-only review after the session completes |

## Access rules

- **Quizzes** (live and offline): the student must have an *active* enrolment in the quiz's section (`403 "You are not enrolled in this section."`). The quiz's lecturer is also allowed.
- **Active-learning sessions**: the student must have an active enrolment in any section of the plan's course (`403 "You are not enrolled in this course."`). Sessions that were never started are `404`.
- Another institution's quiz/session id → `404`.

---

## Shared objects

### QuizCard

```json
{
  "id": 1,
  "type": "quiz",
  "title": "Week 5 Recap",
  "category": "live",              // "live" | "offline"
  "mode": "formative",             // "formative" | "participation" | "graded"
  "mode_label": "Formative",
  "is_anonymous": false,
  "status": "waiting",             // live: waiting | active | reviewing | ended; offline: active | ended
  "join_code": "LOXNAI",
  "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" },
  "section": { "id": 1, "name": "Section 01" },
  "question_count": 2,
  "available_from": null,          // offline only
  "available_until": null,         // offline only — "due" time
  "started_at": null,
  "ended_at": null,
  "me": {
    "joined": false,
    "display_name": null,          // "Player 482" for anonymous quizzes
    "score": 0,
    "answered_count": 0,
    "completed": false             // offline: submitted; live: quiz reviewing/ended and I joined
  }
}
```

### SessionCard

```json
{
  "id": 1,
  "type": "session",
  "title": "Week 5: Derivatives",   // plan title
  "status": "active",               // active | completed
  "join_code": "QO8JER",
  "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" },
  "week_number": 5,                  // nullable
  "total_activities": 3,
  "started_at": "2026-09-11T14:29:33+00:00",
  "ended_at": null,
  "joined": false
}
```

### Question (quiz)

While a question is **open** it never contains `is_correct`, `correct_option_id` or `explanation`:

```json
{
  "session_question_id": 1,         // send this back when answering
  "number": 1,
  "total": 2,
  "type": "mcq",                     // "mcq" | "true_false" | "short_answer"
  "text": "Question 1",
  "points": 2,
  "time_limit": 30,                  // seconds
  "status": "active",                // pending | active | closed
  "opened_at": "2026-09-11T14:29:33+00:00",
  "closed_at": null,
  "options": [
    { "id": 1, "label": "A", "text": "Option A" },
    { "id": 2, "label": "B", "text": "Option B" },
    { "id": 3, "label": "C", "text": "Option C" }
  ]
}
```

Revealed form (question closed, or in a result) adds `options[].is_correct`, `correct_option_id`, `explanation` (nullable).
`short_answer` questions have `options: []` and are never auto-marked correct (web parity).

### Activity (active learning)

Never contains poll answers or the lecturer's solution.

```json
{
  "id": 1,
  "number": 1,
  "title": "Warm-up poll",
  "type": "individual",              // individual | pair | group | discussion | reflection | whole_class
  "type_label": "Individual",        // localized
  "description": null,
  "instructions": "<p>Which rule applies to <strong>sin(x²)</strong>?</p>",   // may be HTML (rich editor)
  "instructions_text": "Which rule applies to sin(x²)?",                      // plain-text fallback (instructions, else description)
  "duration_minutes": 5,             // nullable
  "response_mode": "individual",     // individual | group
  "response_type": "mcq",            // none | text | mcq | reflection
  "multi_select": false,             // mcq: allow several options
  "max_length": null,                // text: 2000, reflection: 500
  "poll_options": [ { "id": 1, "label": "Chain rule" }, { "id": 2, "label": "Product rule" } ]
}
```

### ActivityResponse

```json
{
  "response_id": 1,
  "response_type": "mcq",
  "text": null,                      // text/reflection
  "selected_option_ids": [1],        // mcq
  "submitted_at": "2026-09-11T14:29:33+00:00"
}
```

---

## GET `live/hub`

Lists (for the student's active section enrolments):
- `active_sessions` — active-learning sessions in progress (SessionCard[])
- `live_quizzes` — live quizzes with status `waiting | active | reviewing`, updated within the last
  `QUIZ_AUTO_CLOSE_HOURS` (default 3), so a replayed copy never started or a run never ended drops off (QuizCard[]).
  Such quizzes are also ended automatically once idle that long
- `offline_quizzes` — offline quizzes open right now (`available_from ≤ now ≤ available_until`, not ended). `me.completed` tells whether the student already submitted.
- `recent_sessions` — up to 10 completed sessions the student joined (latest first)
- `recent_quizzes` — up to 10 quizzes the student joined that ended (or offline quizzes past their due time)

```json
{
  "data": {
    "active_sessions": [ { "id": 1, "type": "session", "title": "Week 5: Derivatives", "status": "active", "join_code": "QO8JER", "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" }, "week_number": 5, "total_activities": 3, "started_at": "2026-09-11T14:29:33+00:00", "ended_at": null, "joined": false } ],
    "live_quizzes": [ { "id": 1, "type": "quiz", "title": "Week 5 Recap", "category": "live", "mode": "formative", "mode_label": "Formative", "is_anonymous": false, "status": "waiting", "join_code": "LOXNAI", "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" }, "section": { "id": 1, "name": "Section 01" }, "question_count": 2, "available_from": null, "available_until": null, "started_at": null, "ended_at": null, "me": { "joined": false, "display_name": null, "score": 0, "answered_count": 0, "completed": false } } ],
    "offline_quizzes": [ { "id": 2, "type": "quiz", "title": "Chapter 3 Practice", "category": "offline", "mode": "formative", "mode_label": "Formative", "is_anonymous": false, "status": "active", "join_code": "RB1DSC", "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" }, "section": { "id": 1, "name": "Section 01" }, "question_count": 2, "available_from": "2026-09-11T13:29:33+00:00", "available_until": "2026-09-12T14:29:33+00:00", "started_at": "2026-09-11T13:29:33+00:00", "ended_at": null, "me": { "joined": false, "display_name": null, "score": 0, "answered_count": 0, "completed": false } } ],
    "recent_sessions": [],
    "recent_quizzes": [],
    "server_time": "2026-09-11T14:29:33+00:00"
  }
}
```

Navigation: session → `live/sessions/{id}` (if `status` completed → review); quiz → `live/quizzes/{id}`.

## POST `live/join`

Body: `{ "code": "qo8jer" }` — trimmed and upper-cased server-side; must be exactly 6 characters.
Lookup order (web parity): active active-learning session with that code → quiz with that code whose status is `waiting | active | reviewing`. The student is registered as a participant.

```json
{ "message": "You joined the session.", "data": { "type": "session", "id": 1, "title": "Week 5: Derivatives", "category": null } }
```
```json
{ "message": "You joined the quiz.", "data": { "type": "quiz", "id": 1, "title": "Week 5 Recap", "category": "live" } }
```

Errors:
- `422` `{"message": "Invalid or expired join code.", "errors": {"code": ["Invalid or expired join code."]}}` — unknown code, ended quiz, completed session (the message is localized en/ms).
- `422` `errors.code` — not 6 characters.
- `403 "You are not enrolled in this course."` (session) / `403 "You are not enrolled in this section."` (quiz).
- `422 "This quiz is not currently available."` — offline quiz outside its window.

---

## Quizzes

### GET `live/quizzes/{quiz}`

Bootstrap. QuizCard fields plus:

| Field | Meaning |
|---|---|
| `total_points` | Sum of question points |
| `playable` | live: status `waiting/active/reviewing`; offline: inside availability window and not ended |
| `can_view_result` | `GET result` will succeed |
| `questions` | Offline + playable + not yet submitted: all questions (open form, no answers). Otherwise `[]` |
| `server_time` | |

When `playable` the student is registered as a participant (idempotent).

Live quiz (go to the state screen and start polling):
```json
{
  "data": {
    "id": 1, "type": "quiz", "title": "Week 5 Recap", "category": "live", "mode": "formative", "mode_label": "Formative",
    "is_anonymous": false, "status": "waiting", "join_code": "LOXNAI",
    "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" },
    "section": { "id": 1, "name": "Section 01" },
    "question_count": 2, "available_from": null, "available_until": null, "started_at": null, "ended_at": null,
    "me": { "joined": true, "display_name": "Nur Aina", "score": 0, "answered_count": 0, "completed": false },
    "total_points": 4, "playable": true, "can_view_result": false, "questions": [],
    "server_time": "2026-09-11T14:29:33+00:00"
  }
}
```

Offline quiz, open, not submitted (render the whole form, one option per question):
```json
{
  "data": {
    "id": 2, "type": "quiz", "title": "Chapter 3 Practice", "category": "offline", "mode": "formative", "mode_label": "Formative",
    "is_anonymous": false, "status": "active", "join_code": "RB1DSC",
    "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" },
    "section": { "id": 1, "name": "Section 01" },
    "question_count": 2,
    "available_from": "2026-09-11T13:29:33+00:00", "available_until": "2026-09-12T14:29:33+00:00",
    "started_at": "2026-09-11T13:29:33+00:00", "ended_at": null,
    "me": { "joined": true, "display_name": "Nur Aina", "score": 0, "answered_count": 0, "completed": false },
    "total_points": 4, "playable": true, "can_view_result": false,
    "questions": [
      { "session_question_id": 3, "number": 1, "total": 2, "type": "mcq", "text": "Question 1", "points": 2, "time_limit": 30, "status": "active", "opened_at": "2026-09-11T14:29:33+00:00", "closed_at": null,
        "options": [ { "id": 7, "label": "A", "text": "Option A" }, { "id": 8, "label": "B", "text": "Option B" }, { "id": 9, "label": "C", "text": "Option C" } ] },
      { "session_question_id": 4, "number": 2, "total": 2, "type": "mcq", "text": "Question 2", "points": 2, "time_limit": 30, "status": "active", "opened_at": "2026-09-11T14:29:33+00:00", "closed_at": null,
        "options": [ { "id": 10, "label": "A", "text": "Option A" }, { "id": 11, "label": "B", "text": "Option B" }, { "id": 12, "label": "C", "text": "Option C" } ] }
    ],
    "server_time": "2026-09-11T14:29:33+00:00"
  }
}
```

Offline already submitted → `me.completed: true`, `can_view_result: true`, `questions: []` → open the result.
Not playable and never joined → `me.joined: false`, `can_view_result: false` (show "not available").

Errors: `403 "You are not enrolled in this section."`, `404`.

### GET `live/quizzes/{quiz}/state` (live quizzes only)

Poll every ~2 s while the screen is visible; stop when `phase == "finished"`. The session-wide part is cached ~2 s server-side.

| Field | Values |
|---|---|
| `status` | `waiting` · `active` · `reviewing` · `ended` |
| `phase` | `lobby` (waiting for lecturer) · `answering` (a question is open) · `reveal` (last question closed; answer + standings shown) · `finished` (reviewing/ended) |
| `participant_count` | joined students |
| `question_total` | |
| `question` | `answering`: open Question + `remaining_seconds` (int). The timer is enforced: answers more than 3 s after it runs out are refused, and the lecturer's board closes the question when it hits 0 or everyone has answered. `reveal`: revealed Question + `remaining_seconds: null`. Otherwise `null` |
| `me` | `joined`, `display_name` (the nickname shown on the board), `score` (marks), `points` (game points), `answered`, `selected_option_id`, `answer_text` (for the current question); reveal only: `is_correct` + `points_earned` (marks; both `null` if no answer = "Time's up") and `points_gained` (game points); reveal/finished: `rank` + `previous_rank` |
| `leaderboard` | reveal/finished: top 10 `[{rank, previous_rank, name, score, points, gained, is_me}]`, else `null`. Names are nicknames (pseudonyms for anonymous quizzes) |

**Game points (Mentimeter style).** Live quizzes rank by game points, not marks. A correct answer earns
500–1000 points: 1000 at once, falling linearly to 500 at the time limit, timed by the server from
when the question opened (`response_time_ms` from the app is ignored). Wrong or no answer earns 0.
Marks (`score`, `points_earned`) are unchanged — the question's points for a correct answer — and are
what grading and `GET result`'s `score`/`max_score` use. Ranking order: points, then marks; equal on
both share a rank (1, 1, 3). `previous_rank` is the rank before the question just revealed (`null`
after the first question, when nobody had scored yet), so `previous_rank - rank` is the movement (▲/▼);
`gained` is what that question added. `GET result` adds `points` and, for live quizzes, ranks by them.

Lobby:
```json
{
  "data": {
    "server_time": "2026-09-11T14:29:33+00:00",
    "status": "waiting", "phase": "lobby", "participant_count": 1, "question_total": 2, "question": null,
    "me": { "joined": true, "display_name": "Nur Aina", "score": 0, "answered": false, "selected_option_id": null, "answer_text": null, "is_correct": null, "points_earned": null, "rank": null },
    "leaderboard": null
  }
}
```

Answering (not answered yet):
```json
{
  "data": {
    "server_time": "2026-09-11T14:29:33+00:00",
    "status": "active", "phase": "answering", "participant_count": 2, "question_total": 2,
    "question": {
      "session_question_id": 1, "number": 1, "total": 2, "type": "mcq", "text": "Question 1", "points": 2, "time_limit": 30,
      "status": "active", "opened_at": "2026-09-11T14:29:33+00:00", "closed_at": null,
      "options": [ { "id": 1, "label": "A", "text": "Option A" }, { "id": 2, "label": "B", "text": "Option B" }, { "id": 3, "label": "C", "text": "Option C" } ],
      "remaining_seconds": 30
    },
    "me": { "joined": true, "display_name": "Nur Aina", "score": 0, "answered": false, "selected_option_id": null, "answer_text": null, "is_correct": null, "points_earned": null, "rank": null },
    "leaderboard": null
  }
}
```

Answering (already answered — lock the options, highlight my choice; correctness is not revealed):
```json
"me": { "joined": true, "display_name": "Nur Aina", "score": 2, "answered": true, "selected_option_id": 2, "answer_text": null, "is_correct": null, "points_earned": null, "rank": null }
```
(`score` already includes points of answered questions.)

Reveal:
```json
{
  "data": {
    "server_time": "2026-09-11T14:29:33+00:00",
    "status": "active", "phase": "reveal", "participant_count": 2, "question_total": 2,
    "question": {
      "session_question_id": 1, "number": 1, "total": 2, "type": "mcq", "text": "Question 1", "points": 2, "time_limit": 30,
      "status": "closed", "opened_at": "2026-09-11T14:29:33+00:00", "closed_at": "2026-09-11T14:29:48+00:00",
      "options": [
        { "id": 1, "label": "A", "text": "Option A", "is_correct": false },
        { "id": 2, "label": "B", "text": "Option B", "is_correct": true },
        { "id": 3, "label": "C", "text": "Option C", "is_correct": false }
      ],
      "correct_option_id": 2, "explanation": "Explanation 1", "remaining_seconds": null
    },
    "me": { "joined": true, "display_name": "Nur Aina", "score": 2, "points": 935, "answered": true, "selected_option_id": 2, "answer_text": null, "is_correct": true, "points_earned": 2, "points_gained": 935, "rank": 1, "previous_rank": null },
    "leaderboard": [
      { "rank": 1, "previous_rank": null, "name": "Nur Aina", "score": 2, "points": 935, "gained": 935, "is_me": true },
      { "rank": 2, "previous_rank": null, "name": "Hafiz Rahman", "score": 0, "points": 0, "gained": 0, "is_me": false }
    ]
  }
}
```

Finished (show final score + rank; offer `GET result`):
```json
{
  "data": {
    "server_time": "2026-09-11T14:29:33+00:00",
    "status": "ended", "phase": "finished", "participant_count": 2, "question_total": 2, "question": null,
    "me": { "joined": true, "display_name": "Nur Aina", "score": 2, "answered": false, "selected_option_id": null, "answer_text": null, "is_correct": null, "points_earned": null, "rank": 1 },
    "leaderboard": [
      { "rank": 1, "name": "Nur Aina", "score": 2, "is_me": true },
      { "rank": 2, "name": "Hafiz Rahman", "score": 0, "is_me": false }
    ]
  }
}
```

Errors: `422 "This quiz is not a live quiz."` (offline quiz), `403`, `404`.

### POST `live/quizzes/{quiz}/nickname` (live quizzes only)

Sets the name shown on the lecturer's board and leaderboards (offer it in the lobby). Joins the student if needed.

Body: `{ "nickname": "Pipe Master" }` — required, max 30 characters; control characters are removed and
spaces collapsed.

Success (`200`): `{ "message": "Name saved.", "data": { "display_name": "Pipe Master" } }`

Errors: `422 errors.nickname` (missing, too long, or blank after cleaning: "Please enter a name."),
`422 "This quiz is not running."` (offline or ended), `422 "Names are hidden in this quiz."` (anonymous),
`403 "You are not enrolled in this section."`.

### POST `live/quizzes/{quiz}/respond` (live quizzes only)

Body:
```json
{ "session_question_id": 1, "selected_option_id": 2, "answer_text": null, "response_time_ms": 4200 }
```
- `mcq` / `true_false`: `selected_option_id` required and must belong to the question.
- `short_answer`: `answer_text` required (max 2000).
- One answer per question — it cannot be changed.

Success (`200`) — correctness is not revealed:
```json
{ "message": "Answer submitted.", "data": { "session_question_id": 1, "answered": true, "selected_option_id": 2, "answer_text": null } }
```
Duplicate (`200`, returns the stored answer):
```json
{ "message": "Already answered.", "data": { "session_question_id": 1, "answered": true, "selected_option_id": 2, "answer_text": null } }
```

Errors:
- `403 "You have not joined this quiz."` — call `GET live/quizzes/{quiz}` (or join) first.
- `422 "Question is not active."` — question closed/not open yet (refresh state).
- `422 "Time is up for this question."` — more than 3 s past the question's time limit.
- `422 errors.session_question_id` (missing), `errors.selected_option_id` ("Please choose an answer." / "The selected option is invalid."), `errors.answer_text` ("Please type your answer.").
- `422 "This quiz is not a live quiz."`, `403 "You are not enrolled in this section."`.

### POST `live/quizzes/{quiz}/submit-offline`

Body — map of `session_question_id → option id` (null/omitted = unanswered; at least one key required):
```json
{ "answers": { "3": 8, "4": null } }
```
Scores every question once. Response `201` with the **Result** payload (below):
```json
{ "message": "Quiz submitted successfully!", "data": { "quiz": { "...": "QuizCard, me.completed=true" }, "score": 2, "max_score": 4, "correct_count": 1, "question_count": 2, "accuracy": 50, "rank": 1, "participant_count": 1, "questions": [ "..." ] } }
```

Errors:
- `422 "This quiz is not currently available."` — not offline, outside window, or ended.
- `422 errors.answers` — missing/empty; `errors.answers.<id>` not an integer.
- `403 "You have not joined this quiz."` — open `GET live/quizzes/{quiz}` first.
- `422 "You have already submitted this quiz."`.

### GET `live/quizzes/{quiz}/result`

Available when: offline → submitted, or the window has closed; live → quiz `ended` (or `reviewing` with no open question).

```json
{
  "data": {
    "quiz": {
      "id": 1, "type": "quiz", "title": "Week 5 Recap", "category": "live", "mode": "formative", "mode_label": "Formative",
      "is_anonymous": false, "status": "ended", "join_code": "LOXNAI",
      "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" },
      "section": { "id": 1, "name": "Section 01" },
      "question_count": 2, "available_from": null, "available_until": null,
      "started_at": "2026-09-11T14:29:33+00:00", "ended_at": "2026-09-11T14:35:02+00:00",
      "me": { "joined": true, "display_name": "Nur Aina", "score": 2, "answered_count": 1, "completed": true }
    },
    "score": 2,
    "max_score": 4,
    "correct_count": 1,
    "question_count": 2,
    "accuracy": 50,
    "rank": 1,
    "participant_count": 2,
    "questions": [
      {
        "session_question_id": 1, "number": 1, "total": 2, "type": "mcq", "text": "Question 1", "points": 2, "time_limit": 30,
        "status": "closed", "opened_at": "2026-09-11T14:29:33+00:00", "closed_at": "2026-09-11T14:29:48+00:00",
        "options": [
          { "id": 1, "label": "A", "text": "Option A", "is_correct": false },
          { "id": 2, "label": "B", "text": "Option B", "is_correct": true },
          { "id": 3, "label": "C", "text": "Option C", "is_correct": false }
        ],
        "correct_option_id": 2, "explanation": "Explanation 1",
        "answered": true, "selected_option_id": 2, "answer_text": null, "is_correct": true, "points_earned": 2
      },
      {
        "session_question_id": 2, "number": 2, "total": 2, "type": "mcq", "text": "Question 2", "points": 2, "time_limit": 30,
        "status": "pending", "opened_at": null, "closed_at": null,
        "options": [
          { "id": 4, "label": "A", "text": "Option A", "is_correct": false },
          { "id": 5, "label": "B", "text": "Option B", "is_correct": true },
          { "id": 6, "label": "C", "text": "Option C", "is_correct": false }
        ],
        "correct_option_id": 5, "explanation": "Explanation 2",
        "answered": false, "selected_option_id": null, "answer_text": null, "is_correct": false, "points_earned": 0
      }
    ]
  }
}
```

Errors: `404 "You have not joined this quiz."`, `422 "Submit the quiz to see your result."` (offline, open, not submitted), `422 "Results are available when the quiz ends."` (live), `403`.

---

## Active-learning sessions

### GET `live/sessions/{session}`

SessionCard + `description`, `prerequisites` (plain text, show as a notice when not null), `has_review`, `server_time`. Joins the student when the session is active (idempotent).

```json
{
  "data": {
    "id": 1, "type": "session", "title": "Week 5: Derivatives", "status": "active", "join_code": "QO8JER",
    "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" },
    "week_number": 5, "total_activities": 3,
    "started_at": "2026-09-11T14:29:33+00:00", "ended_at": null, "joined": true,
    "description": null, "prerequisites": null, "has_review": false,
    "server_time": "2026-09-11T14:29:33+00:00"
  }
}
```

`status: "completed"` → go to review. Errors: `403 "You are not enrolled in this course."`, `404` (not started / unknown / other institution).

### GET `live/sessions/{session}/state`

Poll every ~3–5 s. When `current_activity.id` changes, reset the response form and pre-fill from `my_response`.

| Field | Meaning |
|---|---|
| `status` | `active` · `completed` |
| `participant_count` | joined students |
| `total_activities`, `current_index` | "Activity 2 of 3" (`current_index` 0 when none) |
| `responses_open` | session active and the current activity accepts responses |
| `current_activity` | `null` → "Waiting for the lecturer…" (active) or ended screen (completed). Otherwise Activity + fields below |
| `current_activity.response_count` | responses so far |
| `current_activity.activity_started_at` | approximate start of the current activity (last time the lecturer advanced) |
| `current_activity.time_remaining_seconds` | `duration_minutes*60 − elapsed` floored at 0; `null` without a duration |
| `current_activity.my_group` | `{id, name, members: [{id, name, role, is_me}]}` when the student is in a group for this activity (`role`: member · facilitator · reporter · scribe), else `null` |
| `current_activity.my_response` | ActivityResponse or `null` |
| `current_activity.group_response` | group mode: a groupmate's submission `{submitted_by, ...ActivityResponse}` or `null` |

MCQ activity, not answered:
```json
{
  "data": {
    "session_id": 1, "status": "active", "participant_count": 1,
    "current_activity": {
      "id": 1, "number": 1, "title": "Warm-up poll", "type": "individual", "type_label": "Individual",
      "description": null,
      "instructions": "<p>Which rule applies to <strong>sin(x²)</strong>?</p>",
      "instructions_text": "Which rule applies to sin(x²)?",
      "duration_minutes": 5, "response_mode": "individual", "response_type": "mcq", "multi_select": false, "max_length": null,
      "poll_options": [ { "id": 1, "label": "Chain rule" }, { "id": 2, "label": "Product rule" }, { "id": 3, "label": "Quotient rule" } ],
      "response_count": 0,
      "activity_started_at": "2026-09-11T14:29:33+00:00", "time_remaining_seconds": 300,
      "my_group": null, "my_response": null, "group_response": null
    },
    "total_activities": 3, "current_index": 1,
    "started_at": "2026-09-11T14:29:33+00:00", "ended_at": null,
    "responses_open": true,
    "server_time": "2026-09-11T14:29:33+00:00"
  }
}
```

Text activity, already answered (show "Response submitted!" with an Edit button):
```json
{
  "data": {
    "session_id": 1, "status": "active", "participant_count": 1,
    "current_activity": {
      "id": 2, "number": 2, "title": "Think-pair-share", "type": "pair", "type_label": "Pair Work",
      "description": null, "instructions": "Explain the chain rule to your partner.", "instructions_text": "Explain the chain rule to your partner.",
      "duration_minutes": 10, "response_mode": "individual", "response_type": "text", "multi_select": false, "max_length": 2000,
      "poll_options": [], "response_count": 1,
      "activity_started_at": "2026-09-11T14:29:33+00:00", "time_remaining_seconds": 600,
      "my_group": null,
      "my_response": { "response_id": 2, "response_type": "text", "text": "Differentiate the outer function, then multiply by the derivative of the inner one.", "selected_option_ids": [], "submitted_at": "2026-09-11T14:29:33+00:00" },
      "group_response": null
    },
    "total_activities": 3, "current_index": 2,
    "started_at": "2026-09-11T14:29:33+00:00", "ended_at": null,
    "responses_open": true,
    "server_time": "2026-09-11T14:29:33+00:00"
  }
}
```

`response_type: "none"` → `responses_open: false`, show "No response needed for this activity."

Completed:
```json
{
  "data": {
    "session_id": 1, "status": "completed", "participant_count": 1, "current_activity": null,
    "total_activities": 3, "current_index": 0,
    "started_at": "2026-09-11T14:29:33+00:00", "ended_at": "2026-09-11T15:10:02+00:00",
    "responses_open": false, "server_time": "2026-09-11T15:10:05+00:00"
  }
}
```

Errors: `403 "You are not enrolled in this course."`, `404`.

### POST `live/sessions/{session}/respond`

Submitting again for the same activity **updates** the response. Allowed only for the current activity while the session is active.

Body:
```json
{ "activity_id": 1, "response_data": { "selected_options": [1] } }
```
```json
{ "activity_id": 2, "response_data": { "text": "Differentiate the outer function..." } }
```
Optional `"group_id": 5` (must be the student's group for this activity) for group-mode activities.

Rules: `mcq` → `selected_options` non-empty ids of this activity's options, exactly one unless `multi_select`; `text` → non-blank, ≤ 2000 chars; `reflection` → non-blank, ≤ 500 chars.

Success `200`:
```json
{
  "message": "Response submitted!",
  "data": { "activity_id": 1, "response_id": 1, "response_type": "mcq", "text": null, "selected_option_ids": [1], "submitted_at": "2026-09-11T14:29:33+00:00" }
}
```

Errors:
- `422` validation: `errors.activity_id`, `errors.response_data` (missing), `errors["response_data.selected_options"]` ("Please select an option." / "The selected option is invalid." / "Please select only one option."), `errors["response_data.text"]` ("Please enter your response." / "Your response may not be longer than 500 characters."), `errors.group_id` ("You are not a member of this group.").
- `422 "Session is not active."`, `422 "This activity is not currently active."` (lecturer advanced — refresh state), `422 "This activity does not accept responses."`, `422 "A group member has already submitted a response."`.
- `403 "You are not enrolled in this course."`, `404`.

### GET `live/sessions/{session}/review`

After completion: SessionCard + `responded_count` + every activity of the plan (Activity + `my_response`).

```json
{
  "data": {
    "id": 1, "type": "session", "title": "Week 5: Derivatives", "status": "completed", "join_code": "QO8JER",
    "course": { "id": 1, "code": "SKMM1203", "title": "Engineering Mathematics" },
    "week_number": 5, "total_activities": 3,
    "started_at": "2026-09-11T14:29:33+00:00", "ended_at": "2026-09-11T15:10:02+00:00", "joined": true,
    "responded_count": 2,
    "activities": [
      {
        "id": 1, "number": 1, "title": "Warm-up poll", "type": "individual", "type_label": "Individual", "description": null,
        "instructions": "<p>Which rule applies to <strong>sin(x²)</strong>?</p>", "instructions_text": "Which rule applies to sin(x²)?",
        "duration_minutes": 5, "response_mode": "individual", "response_type": "mcq", "multi_select": false, "max_length": null,
        "poll_options": [ { "id": 1, "label": "Chain rule" }, { "id": 2, "label": "Product rule" }, { "id": 3, "label": "Quotient rule" } ],
        "my_response": { "response_id": 1, "response_type": "mcq", "text": null, "selected_option_ids": [1], "submitted_at": "2026-09-11T14:29:33+00:00" }
      },
      {
        "id": 2, "number": 2, "title": "Think-pair-share", "type": "pair", "type_label": "Pair Work", "description": null,
        "instructions": "Explain the chain rule to your partner.", "instructions_text": "Explain the chain rule to your partner.",
        "duration_minutes": 10, "response_mode": "individual", "response_type": "text", "multi_select": false, "max_length": 2000,
        "poll_options": [],
        "my_response": { "response_id": 2, "response_type": "text", "text": "Differentiate the outer function, then multiply by the derivative of the inner one.", "selected_option_ids": [], "submitted_at": "2026-09-11T14:29:33+00:00" }
      },
      {
        "id": 3, "number": 3, "title": "Exit ticket", "type": "reflection", "type_label": "Reflection", "description": null,
        "instructions": null, "instructions_text": null,
        "duration_minutes": 3, "response_mode": "individual", "response_type": "reflection", "multi_select": false, "max_length": 500,
        "poll_options": [], "my_response": null
      }
    ],
    "server_time": "2026-09-11T15:12:00+00:00"
  }
}
```

Errors: `422 "This session is still in progress."` (open the live screen instead), `404` (not started), `403`.

---

## Piping Design Tender and Ophir Offshore Tender (`live/tender/…`)

A course game run in class (BTG3333 Student Guide, Sections 3, 6 and 7): two companies (A and B) present each
session, one student **Judge** from each company scores both, a student **Leader** (or deputy) outside both
companies runs and compiles the session, and the lecturer approves it on the web. The game result never
changes marks. Only students on a game's roster see it: anyone else gets `404 "Tender game not found."`.

Each game has a ruleset, sent as the session's `rules` object; everything below describes `piping` unless
marked. `ophir` (BTG2663 Ophir Offshore Tender) differs in:

- **Scoring:** the same seven criteria every session (`technical` 25, `compliance` 25, `data`, `integration`,
  `defence`, `communication`, `progress` 10 each). A Judge picks a band, then a whole **mark** from 0 to the
  criterion's maximum, sent in `level_a`/`level_b`. Each criterion carries `max_level` and `bands`
  (`[{label: Excellent|Good|Fair|Poor, min, max}]`, best first). A company's mark is the average of the
  Judges' marks; a row is flagged when the highest and lowest Judge differ by 20% of the criterion or more;
  Judge totals more than 10 apart (highest to lowest) flag as in Piping. Tie-break: `compliance`, `defence`, `technical`, then sudden death.
- **Judges:** four slots, two per company: `rules.judge_slots` is `{"1": "A", "2": "B", "3": "A", "4": "B"}`
  (Piping sends `{"1": "A", "2": "B"}`). A student holds one slot only, so a student judging one slot is left
  out of the other slots' `eligible_judges`. Scoring compiles once every assigned Judge has submitted.
- **Sessions:** S1–S6 and GF on weeks 5–11. Student Judges score the Grand Final too (no staff slots).
- **Retrenchment:** after S1–S6 the losing MD names **two** students, one call at a time
  (`retrenchment_task.remaining` counts down); no group goes to zero and no company below 9, so a company one
  above the floor names one. A hire-back never takes the winner above its starting size.
- `change_order_note` is the week's mission (`rules.note_label: "Mission"`).

| Method | Path | Who | Purpose |
|---|---|---|---|
| GET | `live/tender` | roster | My games (GameSummary list) |
| GET | `live/tender/games/{game}` | roster | Game with its seven sessions and my task in each |
| GET | `live/tender/sessions/{session}` | roster | Session payload — poll every 3 s while open |
| PATCH | `live/tender/sessions/{session}` | Leader | Setup: `first_company`, `change_order_note` |
| POST | `live/tender/sessions/{session}/judges` | Leader | `{slot: 1–4 (a key of rules.judge_slots), user_id}` — put a student in a slot (replaces an unsubmitted Judge) |
| POST | `live/tender/sessions/{session}/start` | Leader | setup → scoring |
| POST | `live/tender/sessions/{session}/reconcile` | Leader | compiled → reconcile (once, only with flags) |
| POST | `live/tender/sessions/{session}/reconcile/end` | Leader | reconcile → compiled (when the 2 minutes are up) |
| POST | `live/tender/sessions/{session}/announce` | Leader | compiled → announced; `{sudden_death_winner: "A"|"B"}` only when everything is level |
| POST | `live/tender/sessions/{session}/hires` | Leader | `{company, kind: hire|hire_back|pass, consultant_id?}` |
| POST | `live/tender/sessions/{session}/judges/{judge}/paper` | Leader | Multipart: `scores` (JSON string, Card rows), `pitches` (JSON string), `photo` (image) — a paper card copied in, submitted at once |
| POST | `live/tender/sessions/{session}/declare` | Judge | `{conflict: bool}` — `false` confirms the declaration; `true` frees the slot for a substitute |
| PUT | `live/tender/sessions/{session}/card` | Judge | `{scores: [CardRow], pitches?: [Pitch]}` — draft save (any subset of rows) |
| POST | `live/tender/sessions/{session}/card/submit` | Judge | Lock the card |
| POST | `live/tender/sessions/{session}/card/reconciled` | Judge | Done reconciling |
| POST | `live/tender/sessions/{session}/retrench` | losing MD | `{user_id, reason}` |
| POST | `live/tender/sessions/{session}/appeal` | company member | `{body}` (20–3000 chars) |

Every action returns `{"message", "data": Session}` — the same payload as the GET, so the app replaces its
state with it. Rule violations are `422 {"message"}` with a sentence to show as is (e.g. `"Write a line of
evidence for 3D Plant Model."`, `"The winner chooses first."`). Non-Leaders calling Leader routes get
`403 "Only the Leader or deputy runs the session."`; non-Judges calling card routes `403 "You are not a Judge
for this session."`.

### Session status

`setup` → `scoring` → `compiled` ⇄ `reconcile` → `announced`. When the last card is submitted the server moves
`scoring` → `compiled` itself. `review_status` (`null`, `approved`, `queried`) is the lecturer's decision and
sits beside the status; when the lecturer unlocks a card the session goes back to `scoring` and the Leader
announces again.

### GameSummary

```json
{
  "id": 3, "title": "Project TERATAI",
  "course": {"id": 12, "code": "BTG3333", "title": "Piping"},
  "me": {"role": "student", "company": "A", "company_name": "Company A", "group_no": 2, "is_md": false, "status": "active"},
  "companies": [{"key": "A", "name": "Company A", "size": 6, "wins": 1}, {"key": "B", "name": "Company B", "size": 5, "wins": 0}],
  "champion": null
}
```

`me.role`: `student`, `leader` or `deputy` (Leader and deputy have `company: null`). `me.status`: `active` or
`consultant` (retrenched; keeps company and group). `champion` is the Grand Final winner once announced.

The game endpoint adds `sessions`: `[{id, code: "S1"…"S6"|"GF", label: "Session 1"|"Grand Final", week, status,
winner, totals: {A, B} | null, review_status, my_task}]` where `my_task` is `judge`, `reconcile`, `run`
(Leader), `retrench` (losing MD) or `null`. `winner` and `totals` are `null` until announced.

### Session

```json
{
  "id": 21, "game_id": 3, "code": "S1", "label": "Session 1", "week": 5, "status": "scoring",
  "is_grand_final": false, "first_company": "A",
  "change_order_note": "CO-1: design temperature raised to 220 °C",
  "rules": {"key": "piping", "scale": "levels", "note_label": "Change Order",
            "flag_rule": "The Judges differ by 2 or more levels", "retrench_count": 1,
            "judge_slots": {"1": "A", "2": "B"}},
  "scoring_started_at": "…", "reconcile_started_at": null, "announced_at": null, "review_status": null,
  "server_time": "…",
  "companies": {"A": "Company A", "B": "Company B"},
  "criteria": [{"key": "intro", "label": "Introduction and Scope", "max": 35.0, "min_level": 1, "max_level": 4, "bands": []}, …],
  "consultants": [{"user_id": 88, "name": "…", "company": "B"}],
  "result": null,
  "me": { …as GameSummary.me },
  "my_judge": null,
  "leader": null,
  "retrenchment_task": null,
  "my_appeal": null,
  "can_appeal": false
}
```

- `criteria` are only the rows scored this session (Student Guide, Section 6); `min_level` is `0` only for
  `compliance` (no slide). Keys: `intro`, `plant_3d`, `layout`, `pid_iso`, `software`, `analysis`,
  `fabrication`, `compliance`, `change_order`, `defence`.
- `result` once announced: `{winner, tie_break: null|"compliance"|"defence"|"change_order"|"sudden_death",
  totals: {A, B}, appeal_deadline}`. Appeals are open until `appeal_deadline` (noon, institution time, the next day).
- `my_judge` (when I hold a slot): `{id, slot, company, declared, submitted, reconciled, from_paper,
  scores: [CardRow + level_a_after, level_b_after], pitches: [Pitch], reconcile_criteria: [key]}`. It never
  contains the other Judge's levels. `reconcile_criteria` lists the rows I may change during `reconcile`.
- `leader` (Leader/deputy only): `{judges: [{id, slot, name, company, guest, declared, submitted, reconciled,
  from_paper}], eligible_judges: {"1": [{user_id, name, group_no, judged_before}], "2": […], … one per judge slot} | null (setup and
  scoring), sheet: Sheet | null (from compiled on), reconcilable: [key], can_reconcile, pitch_ranking:
  [{user_id, name, total}], retrenchment: {company, status: pending|named|at_floor} | null, hires: [{kind,
  company, user_id, name}], company_sizes: {A, B}}`. `rules.judge_slots` names each slot's company (Piping: slot 1 A, slot 2 B); eligible
  students are sorted by how often they have judged. The retrenched student's name is never in this object.
- `retrenchment_task` (losing MD, S1–S5, until named): `{company, remaining, eligible: [{user_id, name, group_no}]}` —
  only students whose removal keeps every group at 2 and the company at 4; the MD is never listed.
- `my_appeal`: `{body, decision: null|upheld|dismissed, decision_note, created_at}`.

### CardRow and Pitch

`CardRow`: `{criterion, level_a, level_b, evidence}` — levels 1–4 (0–4 on `compliance`), evidence ≤ 500 chars.
Submit requires both levels and non-empty evidence on every row. During `reconcile`, `PUT card` only reads
`level_a`/`level_b` of `reconcile_criteria` rows and stores them as the after-reconcile levels (the originals
are kept).

`Pitch`: `{consultant_id, relevant, credible, standards, communication}`, each 1–4 (total out of 16). Leave a
consultant out if they did not pitch.

### Sheet

```json
{
  "criteria": [{"key": "intro", "label": "…", "max": 35.0,
                "a": {"j1": 4, "j2": 3, "judges": {"1": 4, "2": 3}, "mark": 30.63, "flag": false},
                "b": {"j1": 3, "j2": 3, "judges": {"1": 3, "2": 3}, "mark": 26.25, "flag": false}}, …],
  "totals": {"A": 81.88, "B": 68.13},
  "judge_totals": {"A": {"1": 85.0, "2": 78.75}, "B": {"1": 66.25, "2": 70.0}},
  "totals_flag": {"A": false, "B": false},
  "flag_count": 0, "single_judge": false,
  "winner": "A", "tie_break": null, "needs_sudden_death": false
}
```

`judges` holds each slot's score (null until that card is in); `j1`/`j2` repeat slots 1 and 2 for older
builds. `judge_totals` has one entry per Judge who submitted.

Mark = (J1 + J2) ÷ 8 × max (one Judge: level ÷ 4 × max, `single_judge: true`). A row is flagged when the
Judges differ by 2+ levels; `totals_flag` when one Judge's total for a company is more than 10 marks from the
other's, which opens every row to reconcile. After the announcement the sheet is the snapshot taken then.

---

## Differences from the web app (intentional)

- Live quizzes require section enrolment (the web `play` page only checks it for offline quizzes). Enrolments must be active.
- A chosen option must belong to the question being answered (web accepts any option id).
- Answers need content (option or text); MCQ single-select, text length and group membership are validated for activity responses.
- Enrolment is enforced on session state/review (web checks only on join).
- Offline results are hidden until submission while the quiz is open, so answers can't be previewed.
