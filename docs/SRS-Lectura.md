# Software Requirements Specification — Lectura

**AI-Powered Teaching Management PWA**

Version: 1.0
Date: 2026-03-26
Status: Draft — Pending stakeholder review

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Overview](#2-system-overview)
3. [User Roles](#3-user-roles)
4. [Functional Requirements](#4-functional-requirements)
5. [Non-Functional Requirements](#5-non-functional-requirements)
6. [User Stories and Acceptance Criteria](#6-user-stories-and-acceptance-criteria)
7. [MVP Phasing](#7-mvp-phasing)
8. [Open Questions](#8-open-questions)
9. [Glossary](#9-glossary)

---

## 1. Introduction

### 1.1 Purpose

This document defines the software requirements for **Lectura**, an AI-powered Teaching Management PWA designed to help lecturers manage the full teaching workflow: Plan, Teach, Engage, Assess, Analyse, Improve, and Archive.

### 1.2 Scope

Lectura is a **multi-tenant SaaS platform** built with Laravel 12, targeting Malaysian higher education institutions initially, with architecture extensible to global markets. The MVP targets 5–20 lecturers and 200–1,000 students in a single pilot institution.

### 1.3 Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP) |
| Frontend | Blade + Tailwind CSS |
| Real-time | Laravel Reverb / Pusher-compatible |
| Cache/Queue | Redis |
| File Storage | Google Drive API (metadata in DB) |
| AI | Provider-agnostic (Claude / OpenAI / Gemini) |
| Delivery | PWA (mobile + desktop) |
| Language | English + Bahasa Melayu |

### 1.4 Compliance

- Malaysian PDPA as primary compliance target
- Architecture must support tenant-configurable privacy controls for future GDPR alignment
- WCAG 2.1 AA-inspired accessibility baseline

---

## 2. System Overview

### 2.1 Product Vision

Lectura is a **Lecturer Teaching Workspace** covering the full teaching cycle. It reduces admin work, improves student learning, centralises teaching evidence, and provides actionable intelligence for course improvement.

### 2.2 Tenant Hierarchy

```
Institution (required)
  └── Faculty / School (optional)
       └── Programme / Department (optional)
            └── Course (required)
                 └── Section (required)
```

Minimum required: Institution > Course > Section.

### 2.3 Academic Calendar

- Tenant-level academic calendar templates (e.g., Sem 1: Sep–Jan, Sem 2: Feb–Jul)
- Free-form override at course level for special terms or short courses

---

## 3. User Roles

### 3.1 Super Admin (Platform)

- Provisions and manages institution tenants
- Configures global system defaults
- Monitors platform-wide usage

### 3.2 Tenant Admin / Coordinator

- Manages institution settings, branding, auth, AI policy, storage
- Defines academic structure (faculties, programmes)
- Sets course file completeness checklists
- Views aggregate reports; individual student data only if institution policy allows

### 3.3 Lecturer

- Manages courses, sections, students, attendance, activities, assessments
- Uses AI tools for planning, marking, and feedback
- Manages course file folders
- Can belong to **multiple institutions** with separate permissions per tenant

### 3.4 Student

- Checks in attendance via QR
- Joins live quizzes
- Submits assignments
- Views own marks and feedback only
- Tracks own weak topics

### 3.5 Teaching Assistant (future)

- Assists with class sessions, activities, preliminary review workflows

---

## 4. Functional Requirements

### Module 1: Authentication & Tenant Management

| ID | Requirement | Priority |
|---|---|---|
| FR-1.1 | System shall support email/password authentication | MVP |
| FR-1.2 | System shall support Google OAuth login | MVP |
| FR-1.3 | Architecture shall allow adding SAML/LDAP SSO per tenant | Future |
| FR-1.4 | Super admin shall be able to manually create and configure institution tenants | MVP |
| FR-1.5 | Each tenant shall have isolated data, branding, auth settings, AI settings, and storage settings | MVP |
| FR-1.6 | A lecturer account shall be able to belong to multiple tenants with separate roles/permissions per tenant | MVP |
| FR-1.7 | Self-service tenant onboarding may be added for smaller institutions | Future |
| FR-1.8 | System shall support bilingual UI (English + Bahasa Melayu) with user/tenant language preference | MVP |

### Module 2: Course & Section Management

| ID | Requirement | Priority |
|---|---|---|
| FR-2.1 | Lecturer shall create courses with: code, title, semester, teaching mode, lecture/tutorial/lab format, weekly topic structure, CLO/learning outcomes | MVP |
| FR-2.2 | Lecturer shall create sections under a course with student capacity | MVP |
| FR-2.3 | CLOs shall be entered manually or pasted, with optional template reuse across courses | MVP |
| FR-2.4 | AI extraction of CLOs from syllabus documents (PDF/Word) | Future |
| FR-2.5 | Course may optionally be assigned to a Faculty and Programme within the tenant hierarchy | MVP |
| FR-2.6 | Tenant admin may define default academic calendar templates; lecturer may override per course | MVP |

### Module 3: Student Roster Management

| ID | Requirement | Priority |
|---|---|---|
| FR-3.1 | Lecturer shall add students via CSV import | MVP |
| FR-3.2 | Lecturer shall add students manually (name, email, student ID) | MVP |
| FR-3.3 | Students shall self-enroll into a section using an invite code | MVP |
| FR-3.4 | System shall prevent duplicate student enrollment in the same section | MVP |
| FR-3.5 | SIS integration for automatic roster sync | Future |

### Module 4: Teaching Plan Builder

| ID | Requirement | Priority |
|---|---|---|
| FR-4.1 | Lecturer shall input course info, number of weeks, topics by week, class format, CLO mapping, and lesson duration | MVP |
| FR-4.2 | AI shall generate a weekly teaching plan including: lesson flow, active learning ideas, online alternatives, case studies, group activities, time allocation, formative checks, assessment alignment suggestions | MVP |
| FR-4.3 | Lecturer shall be able to edit, accept, or regenerate AI suggestions | MVP |
| FR-4.4 | Teaching plan shall be viewable in-app | MVP |
| FR-4.5 | Teaching plan shall be exportable to PDF | MVP |
| FR-4.6 | Teaching plan shall be exportable to Word and Excel | Future |
| FR-4.7 | System shall maintain version history of teaching plans with timestamps, editor identity, and optional change notes | MVP |
| FR-4.8 | Lecturer shall be able to view and compare previous plan versions | MVP |

### Module 5: QR Attendance

| ID | Requirement | Priority |
|---|---|---|
| FR-5.1 | Lecturer shall start an attendance session and generate a dynamic QR code | MVP |
| FR-5.2 | QR code shall rotate every 30 seconds by default | MVP |
| FR-5.3 | Lecturer shall be able to configure QR mode: fixed (low-security) or rotating (normal) | MVP |
| FR-5.4 | Student shall scan QR via PWA to check in while authenticated | MVP |
| FR-5.5 | System shall prevent duplicate check-in from the same student account per session | MVP |
| FR-5.6 | System shall flag late attendance based on configurable time window | MVP |
| FR-5.7 | Lecturer shall be able to manually override attendance (mark present/absent/late) | MVP |
| FR-5.8 | Attendance sessions shall support types: lecture, tutorial, lab, extra/replacement class | MVP |
| FR-5.9 | System shall display attendance history and analytics per section | MVP |
| FR-5.10 | System shall alert lecturer of repeated student absences | MVP |
| FR-5.11 | Attendance reports shall be exportable to Excel and PDF | MVP |
| FR-5.12 | Students may check in from phone or laptop browser | MVP |
| FR-5.13 | Optional device/session logging for fraud detection | MVP |
| FR-5.14 | Internet is required for check-in; no offline attendance in MVP | MVP |
| FR-5.15 | Location-based validation for attendance | Future |

### Module 6: Active Learning Engine

| ID | Requirement | Priority |
|---|---|---|
| FR-6.1 | AI shall suggest suitable active learning activities based on: course topic, class size, duration, student performance, delivery mode, desired engagement level | MVP |
| FR-6.2 | Supported activity types shall include: think-pair-share, case discussion, problem-based task, mini debate, concept mapping, scenario analysis, group worksheet, peer teaching, reflection task | MVP |
| FR-6.3 | Lecturer shall accept, modify, or regenerate suggested activities | MVP |
| FR-6.4 | Activities shall be linked to weekly teaching plan entries | MVP |

### Module 7: Live Quiz / Classroom Response System

| ID | Requirement | Priority |
|---|---|---|
| FR-7.1 | Lecturer shall create live quiz sessions with real-time student participation via PWA | MVP |
| FR-7.2 | Supported question types in MVP: MCQ, true/false, short answer | MVP |
| FR-7.3 | Additional question types: word cloud, ranking, matching, timed challenge | Future |
| FR-7.4 | Lecturer shall see real-time: participation count, answer distribution, leaderboard, response accuracy | MVP |
| FR-7.5 | Quiz sessions shall be configurable as: formative (practice), participation mark, or scored assessment | MVP |
| FR-7.6 | Graded quiz scores shall transfer to gradebook | MVP |
| FR-7.7 | Anonymous mode shall hide student identities from classroom display while retaining identity in database for analytics | MVP |
| FR-7.8 | Lecturer shall create questions ad hoc during a session | MVP |
| FR-7.9 | Lecturer shall save questions to a reusable question bank | MVP |
| FR-7.10 | Questions shall be reusable across sessions and semesters | MVP |
| FR-7.11 | System shall support 30–100 concurrent students per session | MVP |
| FR-7.12 | Team-based quiz mode | Future |

### Module 8: Assignment & Quiz Submission

| ID | Requirement | Priority |
|---|---|---|
| FR-8.1 | Lecturer shall create assignments with: title, description, deadline, rubric, answer scheme, marking mode, individual/group format, resubmission rules | MVP |
| FR-8.2 | Students shall submit: PDF, images, typed answers, file attachments | MVP |
| FR-8.3 | Group assignments shall allow one submission per group | MVP |
| FR-8.4 | Default mark shall be shared across group members; lecturer may optionally adjust per member | MVP |
| FR-8.5 | Rubrics shall primarily support structured matrix format (criteria x performance levels) | MVP |
| FR-8.6 | Rubrics shall also support free-text criteria with marks | MVP |
| FR-8.7 | System shall enforce file size limits (PDF: 20–25 MB, images: compressed on upload) | MVP |
| FR-8.8 | Video uploads | Future |

### Module 9: AI-Assisted Marking

| ID | Requirement | Priority |
|---|---|---|
| FR-9.1 | Lecturer shall upload answer scheme, rubric, marking points, expected answers, and marks by question | MVP |
| FR-9.2 | AI shall read typed PDF submissions, extract answers, compare against scheme, and suggest marks per question | MVP |
| FR-9.3 | AI shall provide explanation of awarded marks and confidence score | MVP |
| FR-9.4 | AI shall suggest marks only; lecturer confirms final marks | MVP |
| FR-9.5 | Marking review shall be per-student in MVP | MVP |
| FR-9.6 | Batch review per-question across all students | Future |
| FR-9.7 | AI marking shall support typed PDFs and structured scanned responses only in MVP | MVP |
| FR-9.8 | Handwritten script OCR and marking | Future |

### Module 10: AI Feedback Generator

| ID | Requirement | Priority |
|---|---|---|
| FR-10.1 | AI shall generate constructive feedback per student including: strengths, missing points, misconceptions, revision advice, improvement tips, follow-up suggestions | MVP |
| FR-10.2 | Feedback shall be adaptable based on student performance level (low, average, advanced) | MVP |
| FR-10.3 | Feedback shall be viewable in-app by student | MVP |
| FR-10.4 | Student shall receive in-app notification when feedback is released | MVP |
| FR-10.5 | Optional email alert when feedback is available | MVP |
| FR-10.6 | Downloadable PDF feedback | Future |

### Module 11: Learning Analytics & Risk Detection

| ID | Requirement | Priority |
|---|---|---|
| FR-11.1 | Dashboard showing: marks by assessment, marks by topic, marks by CLO, attendance vs score, class average trend, participation trend | Phase 2 |
| FR-11.2 | System shall identify weak students (at risk of failure) | Phase 2 |
| FR-11.3 | System shall identify weak topics (most poorly understood) | Phase 2 |
| FR-11.4 | AI shall provide insights on attendance-performance relationships | Phase 2 |
| FR-11.5 | AI shall recommend remedial actions | Phase 2 |

### Module 12: Intervention Support

| ID | Requirement | Priority |
|---|---|---|
| FR-12.1 | AI shall suggest intervention actions: revision activities, remedial quizzes, group reshuffling, targeted support, concept review, peer-assisted learning | Phase 2 |

### Module 13: Course File Management

| ID | Requirement | Priority |
|---|---|---|
| FR-13.1 | System shall propose a default course file folder structure when a course is created | MVP |
| FR-13.2 | Lecturer shall be able to rename, add, delete, reorder folders and create subfolders | MVP |
| FR-13.3 | Folder templates shall follow hierarchy: global system default > tenant/institution default > faculty/programme override (optional) > lecturer customisation per course | MVP |
| FR-13.4 | Lecturer shall be able to save their customised folder structure as a reusable template | MVP |
| FR-13.5 | Course file completeness shall be determined by a required document checklist defined by institution/faculty admin | MVP |
| FR-13.6 | Lecturer dashboard shall show: missing required documents, uploaded evidence count, completion percentage | MVP |
| FR-13.7 | Files shall support tagging with: week, CLO, assessment type, section, topic, evidence type, semester, activity type | MVP |
| FR-13.8 | Files shall be searchable and filterable by tags and folder | MVP |

### Module 14: Google Drive Integration

| ID | Requirement | Priority |
|---|---|---|
| FR-14.1 | Lecturer shall authenticate their Google Drive account via OAuth | MVP |
| FR-14.2 | System shall create course folder structure on Drive automatically | MVP |
| FR-14.3 | Files uploaded through the app shall be stored on Google Drive | MVP |
| FR-14.4 | File metadata shall be stored in the application database: file name, type, uploader, related course/section/week/assessment, Drive file ID, folder ID, upload timestamp | MVP |
| FR-14.5 | If lecturer disconnects Drive, file metadata shall remain but files shall be marked as unavailable | MVP |
| FR-14.6 | System shall warn user before disconnecting Drive | MVP |
| FR-14.7 | Institutional shared Drive support | Future |

### Module 15: Notifications

| ID | Requirement | Priority |
|---|---|---|
| FR-15.1 | In-app notification system for all user types | MVP |
| FR-15.2 | Email notifications for important events | MVP |
| FR-15.3 | Student notification triggers: new assignment, deadline reminder, feedback released, live quiz started, attendance session open | MVP |
| FR-15.4 | Lecturer notification triggers: submission received, marking ready for review, weak student alert, course file missing required documents | MVP |
| FR-15.5 | Web push notifications | Future |

### Module 16: Lecturer Dashboard

| ID | Requirement | Priority |
|---|---|---|
| FR-16.1 | Dashboard shall display: today's classes, active courses/sections, attendance status, pending assessments, AI marking queue, weekly teaching progress, course file completeness | MVP |
| FR-16.2 | Dashboard shall display: weak student alerts, weak topic alerts, suggested AI actions | Phase 2 |

---

## 5. Non-Functional Requirements

### 5.1 Performance

| ID | Requirement |
|---|---|
| NFR-1 | Page load time shall be under 3 seconds on standard broadband |
| NFR-2 | Live quiz shall support 30–100 concurrent users per session with response latency under 2 seconds |
| NFR-3 | QR code shall refresh every 30 seconds without page reload |
| NFR-4 | AI marking shall process a single typed PDF submission within 60 seconds |
| NFR-5 | System shall handle 1,000 registered users and 200 concurrent users in MVP |

### 5.2 Security

| ID | Requirement |
|---|---|
| NFR-6 | All data in transit shall use TLS 1.2+ |
| NFR-7 | Passwords shall be hashed using bcrypt or Argon2 |
| NFR-8 | Tenant data isolation must be enforced at application and database query level |
| NFR-9 | API authentication shall use token-based auth (Laravel Sanctum) |
| NFR-10 | Session tokens shall expire after configurable inactivity period |
| NFR-11 | AI processing of student data must be configurable per tenant (enable/disable/restrict) |
| NFR-12 | System shall maintain audit logs for sensitive operations |

### 5.3 Privacy & Compliance

| ID | Requirement |
|---|---|
| NFR-13 | System shall comply with Malaysian PDPA |
| NFR-14 | Students shall only access their own marks, feedback, and attendance records |
| NFR-15 | Tenant admins shall see aggregate data by default; individual data only with explicit policy permission |
| NFR-16 | System shall display consent notice for AI processing of student work |
| NFR-17 | Tenant shall be able to configure data retention policies |

### 5.4 Availability & Reliability

| ID | Requirement |
|---|---|
| NFR-18 | Target uptime: 99.5% excluding scheduled maintenance |
| NFR-19 | Background job failures (AI marking, Drive sync) shall retry with exponential backoff |
| NFR-20 | System shall gracefully degrade if AI provider is unavailable (queued for later processing) |

### 5.5 Scalability

| ID | Requirement |
|---|---|
| NFR-21 | Architecture shall support horizontal scaling of web and queue workers |
| NFR-22 | Database queries shall be scoped to tenant to enable future sharding |

### 5.6 Usability

| ID | Requirement |
|---|---|
| NFR-23 | WCAG 2.1 AA-inspired accessibility: keyboard navigable, readable contrast, proper labels |
| NFR-24 | PWA shall be installable on mobile (iOS/Android) and desktop |
| NFR-25 | Responsive design for mobile-first student experience and desktop-first lecturer experience |
| NFR-26 | Bilingual support (English + Bahasa Melayu) with user/tenant preference |

---

## 6. User Stories and Acceptance Criteria

### Epic 1: Course Setup

**US-1.1** As a lecturer, I want to create a course with code, title, semester, topics, and CLOs so that I can organise my teaching for the semester.

Acceptance Criteria:
- Course creation form includes all required fields
- CLOs can be entered manually or pasted
- Course is linked to the lecturer's active tenant
- Course appears in lecturer dashboard after creation

**US-1.2** As a lecturer, I want to create sections under a course and add students so that I can manage separate class groups.

Acceptance Criteria:
- Section creation with capacity field
- CSV import parses name, email, student ID columns
- Manual add form for individual students
- Invite code is generated per section
- Duplicate students in same section are rejected with error message

**US-1.3** As a student, I want to self-enroll in a section using an invite code so that I can join my class without waiting for the lecturer.

Acceptance Criteria:
- Student enters invite code on enrollment page
- Student is added to section roster upon valid code
- Invalid or expired code shows error
- Student cannot enroll twice in same section

---

### Epic 2: Teaching Plan

**US-2.1** As a lecturer, I want to generate a weekly teaching plan with AI so that I can save time on lesson preparation.

Acceptance Criteria:
- Lecturer inputs weeks, topics, format, CLO mapping, duration
- AI generates plan with lesson flow, activities, time allocation
- Lecturer can edit any part of the generated plan
- Lecturer can regenerate specific weeks

**US-2.2** As a lecturer, I want to export my teaching plan as PDF so that I can submit it to my faculty.

Acceptance Criteria:
- PDF export includes all weeks, topics, activities, CLO mapping
- PDF is formatted professionally with course header information
- Download triggers immediately after clicking export

**US-2.3** As a lecturer, I want to see previous versions of my teaching plan so that I can track changes made during the semester.

Acceptance Criteria:
- Version list shows timestamp, editor, and change note
- Lecturer can view any previous version in read-only mode
- Current version is clearly marked

---

### Epic 3: QR Attendance

**US-3.1** As a lecturer, I want to start an attendance session with a rotating QR code so that students can check in securely.

Acceptance Criteria:
- QR code displays on lecturer's screen
- QR refreshes every 30 seconds
- Session type can be selected (lecture/tutorial/lab/extra)
- Session can be ended manually by lecturer

**US-3.2** As a student, I want to scan a QR code to check in to class so that my attendance is recorded.

Acceptance Criteria:
- PWA opens camera for QR scanning
- Successful scan records timestamp and confirms check-in
- Late check-in is flagged if outside configured window
- Duplicate scan shows "already checked in" message
- Expired QR code shows error

**US-3.3** As a lecturer, I want to export attendance records to Excel so that I can submit them to administration.

Acceptance Criteria:
- Export includes all sessions for selected section
- Columns: student name, ID, date, session type, status (present/late/absent)
- Excel and PDF formats available

---

### Epic 4: Live Quiz

**US-4.1** As a lecturer, I want to run a live MCQ quiz in class so that I can check student understanding in real-time.

Acceptance Criteria:
- Lecturer creates or selects questions
- Students join via PWA with section/session code
- Answers are collected in real-time
- Answer distribution chart updates live
- Leaderboard shows top performers (unless anonymous mode)

**US-4.2** As a lecturer, I want to save quiz questions to a question bank so that I can reuse them next semester.

Acceptance Criteria:
- Questions can be saved with tags (topic, CLO, difficulty)
- Question bank is searchable and filterable
- Questions can be imported into any future quiz session

**US-4.3** As a student, I want to join a live quiz from my phone so that I can participate in class activities.

Acceptance Criteria:
- Student opens PWA and enters session code or scans QR
- Questions appear one at a time with countdown timer
- Answer submission is confirmed visually
- Results/leaderboard shown after each question (if not anonymous)

---

### Epic 5: Assignment & AI Marking

**US-5.1** As a lecturer, I want to create an assignment with a structured rubric so that students know what is expected and AI can assist with marking.

Acceptance Criteria:
- Assignment form includes title, description, deadline, format (individual/group)
- Rubric builder allows criteria rows x performance level columns with marks
- Answer scheme can be uploaded or entered
- Resubmission rules can be configured

**US-5.2** As a student, I want to submit my assignment as a PDF so that my lecturer can review and mark it.

Acceptance Criteria:
- Upload accepts PDF up to 25 MB
- Upload accepts images (auto-compressed)
- Submission timestamp is recorded
- Student receives confirmation notification
- Late submission is flagged if past deadline

**US-5.3** As a lecturer, I want AI to suggest marks for each student's typed PDF submission so that I can mark faster.

Acceptance Criteria:
- AI reads PDF, extracts answers, compares to answer scheme
- AI displays suggested marks per question with explanation
- Confidence score shown per question
- Lecturer can accept, modify, or reject each suggestion
- Final marks are only recorded after lecturer confirmation

**US-5.4** As a student, I want to receive AI-generated feedback on my assignment so that I know how to improve.

Acceptance Criteria:
- Feedback includes strengths, missing points, improvement tips
- Feedback is visible in-app after lecturer releases it
- In-app notification sent when feedback is available
- Email alert sent (if enabled)

**US-5.5** As a lecturer, I want to handle group assignment marking so that group members receive appropriate marks.

Acceptance Criteria:
- One submission slot per group
- Default mark applies to all members
- Lecturer can adjust individual member marks
- All group members see their individual final mark

---

### Epic 6: Course File Management

**US-6.1** As a lecturer, I want the system to create a default folder structure for my course so that I have a starting point for organising evidence.

Acceptance Criteria:
- Default folders are created based on template hierarchy (global > tenant > faculty > lecturer preference)
- Folders appear immediately after course creation
- Default folders include: Course Information, Teaching Plan, Weekly Materials, Attendance Records, Quizzes, Assignments, Rubrics, Student Submissions, Marked Scripts, CLO Reports, Reflection/CQI, Supporting Evidence

**US-6.2** As a lecturer, I want to customise my course folder structure so that it matches my faculty's requirements.

Acceptance Criteria:
- Lecturer can rename, add, delete, merge, reorder folders
- Lecturer can create subfolders
- Changes are saved per-course
- Lecturer can save structure as reusable template

**US-6.3** As a lecturer, I want to tag files with metadata so that I can find them across different views.

Acceptance Criteria:
- Files can be tagged with: week, CLO, assessment type, topic, evidence type
- Files can be filtered by any combination of tags
- Search works across file names and tags

**US-6.4** As a tenant admin, I want to define a required document checklist so that I can track course file compliance.

Acceptance Criteria:
- Admin creates checklist items (e.g., "course outline uploaded", "at least one rubric")
- Checklist can be set at institution or faculty level
- Lecturer dashboard shows completion percentage and missing items

---

### Epic 7: Google Drive Integration

**US-7.1** As a lecturer, I want to connect my Google Drive so that course files are stored in my Drive automatically.

Acceptance Criteria:
- OAuth flow for Google Drive authorisation
- Course folder structure is mirrored on Drive
- Files uploaded in-app are stored on Drive
- File metadata (name, type, Drive ID, folder ID) stored in database

**US-7.2** As a lecturer, I want to be warned before disconnecting Google Drive so that I don't lose access to my files.

Acceptance Criteria:
- Warning dialog explains consequences of disconnection
- After disconnection, files are marked "unavailable" in-app
- Metadata is preserved for reconnection

---

## 7. MVP Phasing

### Phase 1 — MVP (3 months)

| Module | Scope |
|---|---|
| Auth & Tenancy | Email/password, Google OAuth, manual tenant provisioning, multi-tenant isolation |
| Course & Section | Course CRUD, section CRUD, CLO entry, academic calendar |
| Student Roster | CSV import, manual add, invite code enrollment |
| Teaching Plan | AI-generated weekly plan, in-app view, PDF export, versioning |
| QR Attendance | Rotating QR, multi-session-type, Excel/PDF export, late flags, manual override |
| Live Quiz | MCQ/true-false/short-answer, question bank, formative/graded modes, pseudo-anonymous, real-time dashboard |
| Assignment Submission | PDF/image upload, structured rubric, group assignments, deadlines |
| AI Marking | Typed PDF only, per-student review, lecturer confirmation required |
| AI Feedback | Auto-generated feedback, in-app + email notification |
| Course Files | Flexible folders, template hierarchy, completeness checklist, file tagging |
| Google Drive | Lecturer-authorised Drive, auto folder creation, metadata sync |
| Dashboard | Today's classes, active courses, attendance status, pending assessments, AI queue, file completeness |
| Notifications | In-app + email for key events |
| PWA | Installable, responsive, mobile-first student / desktop-first lecturer |
| i18n | English + Bahasa Melayu |

### Phase 2 — Post-MVP

| Module | Scope |
|---|---|
| Analytics | Marks by assessment/topic/CLO, weak students, weak topics, attendance-performance correlation |
| Intervention | AI-suggested remedial actions, group reshuffling, revision activities |
| Advanced Marking | Batch per-question review, handwritten OCR |
| Advanced Quiz | Word cloud, ranking, matching, team-based mode |
| Advanced Files | Institutional shared Drive, PDF feedback download |
| Notifications | Web push notifications |
| Reports | CLO/PLO attainment, CQI automation |
| Auth | SAML/LDAP SSO, self-service tenant onboarding |

---

## 8. Open Questions

| # | Question | Impact | Status |
|---|---|---|---|
| OQ-1 | Should the system support multiple AI providers simultaneously within one tenant, or one provider per tenant at a time? | AI service architecture | Open |
| OQ-2 | What is the exact AI usage quota model per subscription tier? | Billing, rate limiting | Open |
| OQ-3 | Should teaching plans integrate with any external LMS (Moodle, Google Classroom) in future? | API design | Open |
| OQ-4 | How should the system handle semester rollover? Auto-archive courses? Clone to new semester? | Course lifecycle | Open |
| OQ-5 | Should students be able to appeal or request re-marking through the system? | Assignment workflow | Open |
| OQ-6 | What specific fields are required in the CSV import template for student rosters? | Data format | Open |
| OQ-7 | Should the gradebook be a standalone module or embedded within assignment/quiz views? | UI architecture | Open |
| OQ-8 | How should the system handle concurrent AI marking requests when multiple lecturers submit at once? | Queue design | Open |
| OQ-9 | Should there be a student mobile app (native) in future, or PWA-only indefinitely? | Platform strategy | Open |
| OQ-10 | What branding customisation options should be available per tenant? (Logo, colours, domain?) | Tenant config | Open |

---

## 9. Glossary

| Term | Definition |
|---|---|
| CLO | Course Learning Outcome — a measurable learning objective for a course |
| PLO | Programme Learning Outcome — a measurable learning objective for a degree programme |
| CQI | Continuous Quality Improvement — the process of reviewing and improving teaching quality |
| PDPA | Personal Data Protection Act (Malaysia) — data privacy legislation |
| Tenant | An institution registered on the platform with isolated data and configuration |
| Section | A class group within a course (e.g., Section 01, Section 02) |
| Rubric | A structured marking guide defining criteria and performance levels |
| PWA | Progressive Web App — a web application installable on devices with offline-capable features |
| SIS | Student Information System — institutional system managing student records |

---

*End of Software Requirements Specification*
