# Requirements Specification — Active Learning Session Module

**Feature**: Live Active Learning Sessions with AI Generation & Student Mobile Interaction
**Version**: 1.0
**Date**: 2026-03-26
**Status**: Requirements Complete — Ready for Design

---

## Table of Contents

1. [Vision & Goals](#1-vision--goals)
2. [User Roles & Personas](#2-user-roles--personas)
3. [Functional Requirements](#3-functional-requirements)
4. [Non-Functional Requirements](#4-non-functional-requirements)
5. [User Stories & Acceptance Criteria](#5-user-stories--acceptance-criteria)
6. [Data Entities (Conceptual)](#6-data-entities-conceptual)
7. [MVP Phasing](#7-mvp-phasing)
8. [Open Questions](#8-open-questions)

---

## 1. Vision & Goals

### 1.1 Problem Statement

Currently, active learning activities are created by lecturers and only visible to students via the lecturer's screen projection or verbal instruction. Students have no way to:
- See the activity on their own device
- Submit responses digitally during class
- Review activities before or after class

Lecturers create plans one week at a time, with no way to generate a full semester of activities at once or adapt based on student performance data.

### 1.2 Vision

Transform the active learning module into a **live, connected classroom experience** where:
- AI generates a full semester of weekly activities from the teaching plan (Pro)
- Lecturers run guided live sessions during class
- Students view activities and submit responses directly from their mobile devices
- The system learns from engagement data to improve future activity suggestions

### 1.3 Success Metrics

| Metric | Target |
|--------|--------|
| Student participation rate in live sessions | > 80% of present students |
| Time to generate a full semester plan | < 60 seconds |
| Student response submission rate per activity | > 70% |
| Lecturer satisfaction with AI suggestions | Qualitative — "useful" or better |

---

## 2. User Roles & Personas

### 2.1 Lecturer (Primary Creator)

- Creates and manages active learning plans
- Starts and controls live sessions
- Monitors student participation in real-time
- Reviews responses and engagement data
- Uses AI to generate activities (Pro tier)

### 2.2 Student (Participant)

- Joins live sessions automatically (enrolled) or via code
- Views current activity on their mobile device
- Submits responses (text, MCQ, file, group response)
- Reviews past activities and own responses after class

### 2.3 Admin/Coordinator (Observer)

- Views aggregate engagement data across courses
- No direct interaction with live sessions

---

## 3. Functional Requirements

### 3.1 AI Full-Semester Generation (Pro)

#### FR-3.1.1 — Bulk Generation Trigger

The system shall allow a lecturer to generate active learning activities for **all weeks of the semester at once** from the course's teaching plan.

**Inputs to AI:**
- Course code, title, delivery mode
- All course topics with week numbers
- All CLOs with descriptions
- Class size (from enrolled students)
- Optional: lecture notes per topic, teaching plan content
- Optional: past engagement/quiz/attendance summaries (when available)

**Output:**
- One `ActiveLearningPlan` per week/topic
- Each plan contains 2-5 activities with types, instructions, durations, CLO mappings
- Activities follow a pedagogical sequence: opener, core activity, consolidation

#### FR-3.1.2 — Semester Plan Review

After bulk generation, the lecturer shall see all generated weekly plans in an overview and be able to:
- Accept, edit, or delete individual plans
- Accept, edit, or delete individual activities within a plan
- Regenerate a specific week's activities
- Publish individual weeks or all at once

#### FR-3.1.3 — Adaptive Suggestions (Post-MVP)

After at least 3 weeks of session data, the AI should be able to suggest modifications to upcoming weeks based on:
- Student response quality and participation rates
- Quiz scores related to covered CLOs
- Attendance trends
- Activity type engagement patterns (e.g., "discussion had low participation")

### 3.2 Live Session System

#### FR-3.2.1 — Session Lifecycle

A live active learning session follows this lifecycle:

```
[not_started] → [active] → [completed]
```

- **Not Started**: Plan is published, session not yet begun
- **Active**: Lecturer has started the session, students can join and respond
- **Completed**: Session ended, responses locked, data available for review

#### FR-3.2.2 — Lecturer Starts Session

The lecturer shall be able to start a live session from a published active learning plan. Starting a session:
- Creates a session record linked to the plan
- Sets the first activity as the current activity
- Notifies enrolled students (if online) via WebSocket
- Optionally generates a join code for manual entry

#### FR-3.2.3 — Student Joins Session

Students join a live session by:
- **(Auto)** Opening their course dashboard — if a session is active, a banner/prompt appears
- **(Manual)** Entering a join code from the session screen
- **(Push)** Receiving a notification if the app is open

Only students enrolled in the course's sections can join.

#### FR-3.2.4 — Sequential Activity Flow (MVP)

The lecturer controls the pace of activities:
- Only one activity is "current" at a time
- Lecturer advances to the next activity manually
- Students see only the current activity on their device
- A timer shows elapsed time vs. planned duration
- Lecturer can see a participation counter (X of Y students responded)

#### FR-3.2.5 — Lecturer Live Dashboard

During an active session, the lecturer sees:
- Current activity with instructions
- Real-time participation count (responded / joined)
- Response summary (for MCQ: live poll results; for text: word cloud or list)
- Button to advance to next activity
- Button to end session
- List of joined students with response status per activity

#### FR-3.2.6 — Session End

When the lecturer ends the session:
- All pending responses are locked (no more submissions)
- Session status becomes `completed`
- Summary statistics are calculated and stored
- Students see a "session ended" screen with optional review link

### 3.3 Student Mobile Response System

#### FR-3.3.1 — Activity View on Device

When a student joins a live session, they see:
- Session title and course info
- Current activity: title, type badge, instructions, duration timer
- Response input area (type depends on activity configuration)
- "Submit" button
- Status indicator (waiting / current / submitted / completed)

#### FR-3.3.2 — Response Types

Each activity is configured with a response mode. The system shall support:

| Response Type | Input UI | Storage |
|--------------|----------|---------|
| **Text** | Textarea (max 2000 chars) | Text blob |
| **MCQ / Poll** | Radio buttons or checkboxes | Selected option ID(s) |
| **Short Reflection** | Textarea (max 500 chars) | Text blob |
| **File / Image Upload** | File picker + camera capture | File path + metadata |
| **Group Response** | Same as above, but one per group | Linked to group_id |

#### FR-3.3.3 — Response Mode per Activity

Each activity shall have a configurable `response_mode`:
- **individual** — every student submits their own response
- **group** — one student submits on behalf of the group; other members see the submission

For group responses:
- Any group member can submit
- Once submitted, other members see it as "submitted by [name]"
- Group members are determined by the activity's group assignments

#### FR-3.3.4 — Response Submission Rules

- Students can submit only while the activity is "current"
- Students can update their response until the lecturer advances
- Once the activity is no longer current, responses are locked
- Late-joining students can respond to the current activity only

#### FR-3.3.5 — Pre/Post Class View (Secondary)

Students can also view published active learning plans outside of live sessions:
- Accessible from the course detail page
- Shows the full plan with all activities (read-only)
- If session was completed, shows their own submitted responses
- Does not allow new submissions outside a live session

### 3.4 MCQ / Poll Builder (for Activities)

#### FR-3.4.1 — Poll Configuration

For MCQ/poll-type activities, the lecturer shall define:
- Question text
- 2-6 answer options
- Whether single-select or multi-select
- Optional: correct answer (for concept checks)
- Optional: show results to students after submission

#### FR-3.4.2 — Live Poll Results

During a live session, when a poll activity is current:
- Lecturer sees real-time bar chart of responses
- Students see their own selection after submitting
- If configured, students see aggregate results after the lecturer reveals them

### 3.5 Engagement & Analytics

#### FR-3.5.1 — Session Summary

After a session ends, the lecturer can view:
- Total joined students vs. enrolled
- Per-activity response rate
- Per-activity response summary (text list, poll chart, file list)
- Per-student participation (which activities they responded to)
- Session duration vs. planned duration

#### FR-3.5.2 — Data for AI Feedback Loop

The system shall store structured engagement data that can be fed back to the AI for future suggestions:
- Participation rate per activity type
- Average response length for text activities
- Poll answer distribution
- Session completion rate
- Time spent per activity vs. planned

---

## 4. Non-Functional Requirements

### NFR-4.1 — Real-Time Performance

- WebSocket updates must reach students within 2 seconds of lecturer action
- System must support at least 100 concurrent students in a single session
- Response submissions must be acknowledged within 1 second

### NFR-4.2 — Mobile Responsiveness

- Student session view must be fully functional on mobile (320px-428px viewport)
- Touch-friendly inputs: minimum 44px tap targets
- File upload must support camera capture on mobile browsers
- Must work as PWA (no native app required)

### NFR-4.3 — Offline Resilience

- If student loses connection briefly, queued response should submit when reconnected
- Session state should resync on reconnect without requiring page refresh

### NFR-4.4 — Data Integrity

- Responses must be persisted immediately on submission (not just in-memory)
- Session state must be authoritative on the server, not client
- No duplicate submissions from the same student for the same activity

### NFR-4.5 — Tier Gating

- AI bulk generation: Pro only
- AI adaptive suggestions: Pro only
- Live sessions & student responses: Free + Pro (core feature)
- Poll/MCQ builder: Free + Pro

### NFR-4.6 — Scalability

- AI bulk generation for 14-week semester: single API call or max 3 batched calls
- Session data archived after semester ends to maintain query performance

---

## 5. User Stories & Acceptance Criteria

### Epic 1: AI Full-Semester Generation

#### US-1.1 — Generate Full Semester Activities

> As a **lecturer (Pro)**, I want to generate active learning activities for all weeks of my course at once, so that I have a complete semester plan ready to review and publish.

**Acceptance Criteria:**
1. Given I have a course with topics and CLOs defined, when I click "Generate Semester Plan", the system sends all course context to the AI
2. The AI returns activities grouped by week/topic
3. Each week creates a separate `ActiveLearningPlan` with status `draft`
4. I see a semester overview showing all generated weeks
5. Total generation time is under 90 seconds for a 14-week course
6. If generation fails, I see an error message and can retry

#### US-1.2 — Review and Edit Generated Plans

> As a **lecturer**, I want to review AI-generated plans week by week, editing or removing activities as needed before publishing.

**Acceptance Criteria:**
1. I can expand each week to see its activities
2. I can edit any activity's title, type, instructions, duration, response mode
3. I can delete an activity
4. I can regenerate a single week without affecting others
5. I can publish individual weeks or bulk-publish all

---

### Epic 2: Live Session

#### US-2.1 — Start a Live Session

> As a **lecturer**, I want to start a live active learning session from a published plan, so that students can participate in real-time.

**Acceptance Criteria:**
1. Given a published plan, I see a "Start Live Session" button
2. When I start, a session is created and the first activity becomes current
3. A join code is generated and displayed
4. Enrolled students who are online receive a notification
5. I see a live dashboard with the current activity and a participation counter

#### US-2.2 — Join a Live Session (Student)

> As a **student**, I want to join an active learning session from my mobile device, so I can participate in class activities.

**Acceptance Criteria:**
1. When I open my course page and a session is active, I see a prominent banner "Live Session in Progress — Join Now"
2. Tapping the banner takes me to the session view
3. Alternatively, I can enter a join code from the main dashboard
4. I see the current activity with instructions and a response area
5. If I'm not enrolled in the course, I cannot join

#### US-2.3 — Advance Activity

> As a **lecturer**, I want to advance to the next activity when the class is ready, so I control the pace of the session.

**Acceptance Criteria:**
1. I see a "Next Activity" button on my dashboard
2. When I advance, all students see the new activity within 2 seconds
3. The previous activity's responses are locked
4. Students who hadn't responded to the previous activity see it as "skipped"
5. A running timer resets for the new activity

#### US-2.4 — End Session

> As a **lecturer**, I want to end the session when all activities are complete.

**Acceptance Criteria:**
1. I can end the session at any time (even before all activities)
2. All responses are locked
3. Students see "Session Ended" with a link to review their responses
4. I see a summary screen with participation stats

---

### Epic 3: Student Response

#### US-3.1 — Submit Text Response

> As a **student**, I want to type and submit a text response to an activity.

**Acceptance Criteria:**
1. I see a textarea with the character limit displayed
2. I can type and submit my response
3. After submitting, I see a confirmation with my text
4. I can edit my response until the lecturer advances
5. After the activity is no longer current, I cannot edit

#### US-3.2 — Submit Poll/MCQ Response

> As a **student**, I want to select an answer in a poll or concept check.

**Acceptance Criteria:**
1. I see the question with radio buttons (single) or checkboxes (multi)
2. I select my answer and tap submit
3. If configured, I see aggregate results after submitting
4. The lecturer sees a real-time bar chart of responses

#### US-3.3 — Submit File/Image Response

> As a **student**, I want to upload a photo or file as my response.

**Acceptance Criteria:**
1. I see a file picker that also offers camera capture on mobile
2. Accepted formats: jpg, png, pdf (max 10MB)
3. Upload shows progress indicator
4. After upload, I see a thumbnail preview
5. The lecturer sees the uploaded file in the response summary

#### US-3.4 — Submit Group Response

> As a **group member**, I want one person to submit a response on behalf of our group.

**Acceptance Criteria:**
1. Any group member can submit the group response
2. After submission, all other group members see "Submitted by [name]" with the content
3. The submitter can edit until the activity advances
4. Only one submission per group is accepted

#### US-3.5 — View Past Responses

> As a **student**, I want to review my responses after the session ends.

**Acceptance Criteria:**
1. From the course page, I can view completed sessions
2. Each session shows all activities with my responses (or group responses)
3. For polls, I see the aggregate results
4. Read-only — no editing after session end

---

### Epic 4: Engagement Analytics

#### US-4.1 — Session Summary Dashboard

> As a **lecturer**, I want to see a summary of participation after a session.

**Acceptance Criteria:**
1. I see: students joined, per-activity response rate, overall completion rate
2. For text activities: I can read all responses
3. For polls: I see the results chart
4. For files: I can download/view submissions
5. I see per-student participation (responded / skipped / absent)

---

## 6. Data Entities (Conceptual)

These are the **new entities** required beyond the existing active learning tables:

### 6.1 `active_learning_sessions`

| Field | Type | Purpose |
|-------|------|---------|
| id | PK | |
| tenant_id | FK | Tenant scope |
| plan_id | FK | Links to active_learning_plans |
| status | enum | not_started, active, completed |
| join_code | string(6) | Student join code |
| current_activity_id | FK nullable | Currently active activity |
| started_at | timestamp | When lecturer started |
| ended_at | timestamp nullable | When lecturer ended |
| created_by | FK | Lecturer who started |

### 6.2 `active_learning_session_participants`

| Field | Type | Purpose |
|-------|------|---------|
| id | PK | |
| session_id | FK | |
| user_id | FK | Student |
| joined_at | timestamp | When student joined |
| left_at | timestamp nullable | If student disconnected |

### 6.3 `active_learning_responses`

| Field | Type | Purpose |
|-------|------|---------|
| id | PK | |
| tenant_id | FK | Tenant scope |
| session_id | FK | Live session |
| activity_id | FK | Which activity |
| user_id | FK | Responding student |
| group_id | FK nullable | If group response |
| response_type | enum | text, mcq, file |
| response_data | JSON | Text content, selected options, file metadata |
| submitted_at | timestamp | |
| updated_at | timestamp | Last edit time |

**Unique constraint**: (session_id, activity_id, user_id) for individual mode; (session_id, activity_id, group_id) for group mode.

### 6.4 `active_learning_poll_options` (for MCQ activities)

| Field | Type | Purpose |
|-------|------|---------|
| id | PK | |
| activity_id | FK | |
| label | string | Option text |
| sort_order | int | Display order |
| is_correct | boolean | For concept checks |

### 6.5 Modifications to Existing Tables

**`active_learning_activities`** — add:
- `response_mode` enum: individual, group
- `response_type` enum: text, mcq, file, reflection, none
- `poll_config` JSON nullable: { multi_select: bool, show_results: bool }

---

## 7. MVP Phasing

### Phase 1 — Live Session + Student Responses (Priority)

**Scope:**
- Session lifecycle (start, advance, end)
- Student join (auto-detect + code)
- Text responses (individual + group)
- MCQ/poll responses with live results
- Lecturer live dashboard with participation counter
- Student post-session review
- WebSocket for real-time sync (Laravel Reverb)

**Why first:** This creates the core classroom interaction loop — the highest-value behavioral change.

### Phase 2 — AI Full-Semester Generation

**Scope:**
- Bulk generation from teaching plan + topics + CLOs
- Semester overview UI for review/edit
- Per-week regeneration
- Bulk publish
- Response mode + type configuration in activities

**Why second:** Builds on existing AI generation infrastructure. Higher quality input (full context) produces better output.

### Phase 3 — File Responses + Enhanced Analytics

**Scope:**
- File/image upload responses
- Camera capture on mobile
- Session summary dashboard
- Per-student participation tracking
- Export session data

### Phase 4 — Adaptive AI Suggestions (Post-MVP)

**Scope:**
- AI reads past session engagement data
- Suggests modifications to upcoming weeks
- Engagement trend visualization
- Self-paced session mode (optional)

---

## 8. Open Questions

| # | Question | Impact | Decision Needed By |
|---|----------|--------|--------------------|
| 1 | Should live sessions use Laravel Reverb (existing) or a separate WebSocket solution? | Architecture | Phase 1 design |
| 2 | Should poll results be visible to students in real-time or only after lecturer reveals? | UX | Phase 1 design |
| 3 | Maximum file upload size for student responses (currently 25MB for course files)? | Storage | Phase 3 design |
| 4 | Should the AI bulk generation use one large prompt or batch per-week? | Cost/performance | Phase 2 design |
| 5 | Should session join be restricted to students with attendance marked present? | Pedagogy | Phase 1 design |
| 6 | Should group responses support real-time collaborative editing (Google Docs style)? | Complexity | Post-MVP |
| 7 | Should the system support anonymous responses for sensitive reflection activities? | Pedagogy | Phase 2 |

---

## Appendix: Flow Diagrams (Text)

### Lecturer Flow (Live Session)

```
Published Plan
    |
    v
[Start Live Session] --> Session created, join code generated
    |
    v
[Activity 1 is Current] --> Students see activity + respond
    |                        Lecturer sees participation count
    v
[Next Activity] ----------> Previous locked, Activity 2 current
    |
    v
  ... repeat ...
    |
    v
[End Session] ------------> All responses locked
    |                        Summary calculated
    v
[Review Summary] ---------> Per-activity stats, responses, participation
```

### Student Flow (Mobile)

```
Open Course Page
    |
    +--> [Banner: Live Session Active] --> Tap to Join
    |                                         |
    |                                         v
    |                                  [Session View]
    |                                   - See current activity
    |                                   - Type/select response
    |                                   - Submit
    |                                   - Wait for next activity
    |                                         |
    |                                         v
    |                                  [Session Ended]
    |                                   - Review responses
    |
    +--> [View Published Plans] --> Read-only activity list
              |
              +--> [View Past Session] --> See own responses
```

### AI Semester Generation Flow

```
Lecturer opens course Active Learning page
    |
    v
[Generate Semester Plan] (Pro only)
    |
    v
System collects: topics, CLOs, class size, teaching plan
    |
    v
AI generates activities for all weeks
    |
    v
[Semester Overview] --> Draft plans for each week
    |
    +-- Edit / Delete / Regenerate per week
    |
    v
[Publish] --> Plans available for live sessions
```
