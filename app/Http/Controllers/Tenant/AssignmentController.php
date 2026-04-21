<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentGroupMember;
use App\Models\Course;
use App\Services\GoogleDriveService;
use App\Models\Feedback;
use App\Models\MarkingSuggestion;
use App\Models\Rubric;
use App\Models\RubricCriteria;
use App\Models\RubricLevel;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\StudentGroupSet;
use App\Notifications\AssignmentPublished;
use App\Notifications\FeedbackReleased;
use App\Notifications\SubmissionReceived;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    use AuthorizesCourseAccess;
    public function index(): View
    {
        $user = auth()->user();
        $role = $user->roleInTenant(app('current_tenant')->id);

        if ($role === 'student') {
            // Student: show assignments from enrolled courses, grouped by course
            // Only show top-level assignments or sub-assignments directly
            $sectionIds = $user->sections()->pluck('sections.id');
            $courseIds = Section::whereIn('id', $sectionIds)->pluck('course_id')->unique();

            // Get both parent and sub assignments, but we'll organize them
            $allAssignments = Assignment::whereIn('course_id', $courseIds)
                ->where('status', 'published')
                ->with('course', 'parent', 'subAssignments')
                ->latest('deadline')
                ->get();

            // Filter: only show top-level for students, sub-assignments are shown within parent
            $assignments = $allAssignments->filter(fn ($a) => $a->parent_id === null);

            $assignmentsByCourse = $assignments
                ->groupBy('course_id')
                ->map(fn ($items) => [
                    'course' => $items->first()->course,
                    'assignments' => $items,
                ])
                ->sortBy(fn ($group) => $group['course']->code);

            return view('tenant.assignments.student-index', compact('assignmentsByCourse'));
        }

        // Lecturer: show top-level assignments with sub-assignments nested
        $courses = Course::whereIn('id', $this->accessibleCourseIds())->get();
        $assignments = Assignment::whereIn('course_id', $courses->pluck('id'))
            ->topLevel()
            ->withCount('submissions')
            ->with('course', 'subAssignments')
            ->latest()
            ->get();

        return view('tenant.assignments.index', compact('assignments', 'courses'));
    }

    public function create(Assignment $parent = null): View
    {
        $courses = Course::whereIn('id', $this->accessibleCourseIds())->with('sections', 'learningOutcomes')->get();
        return view('tenant.assignments.create', compact('courses', 'parent'));
    }

    public function edit(string $tenantSlug, Assignment $assignment): View
    {
        $this->authorizeCourseAccess($assignment->course);

        $courses = Course::whereIn('id', $this->accessibleCourseIds())->with('sections', 'learningOutcomes')->get();
        $assignment->load(['rubric.criteria.levels', 'studentGroupSet.groups.members.user']);

        return view('tenant.assignments.edit', compact('assignment', 'courses'));
    }

    public function update(Request $request, string $tenantSlug, Assignment $assignment): RedirectResponse
    {
        $this->authorizeCourseAccess($assignment->course);

        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'total_marks' => ['required', 'numeric', 'min:1'],
            'deadline' => ['nullable', 'date'],
            'type' => ['required', 'in:individual,group'],
            'student_group_set_id' => ['nullable', 'required_if:type,group', 'exists:student_group_sets,id'],
            'marking_mode' => ['required', 'in:manual,ai_assisted'],
            'submission_type' => ['required', 'in:file,text,both'],
            'answer_scheme' => ['nullable', 'string'],
            'answer_scheme_file' => ['nullable', 'file', 'max:25600', 'mimes:pdf'],
        ]);

        $this->validateGroupSetBelongsToCourse($request);

        $tenant = app('current_tenant');

        $schemeData = [
            'answer_scheme' => $request->answer_scheme,
        ];

        if ($request->hasFile('answer_scheme_file')) {
            if ($assignment->answer_scheme_path && \Storage::disk('local')->exists($assignment->answer_scheme_path)) {
                \Storage::disk('local')->delete($assignment->answer_scheme_path);
            }
            $file = $request->file('answer_scheme_file');
            $schemeData['answer_scheme_path'] = $file->store('answer-schemes', 'local');
            $schemeData['answer_scheme_filename'] = $file->getClientOriginalName();
        }

        $assignment->update([
            'course_id' => $request->course_id,
            'title' => $request->title,
            'description' => $request->description,
            'total_marks' => $request->total_marks,
            'deadline' => $request->deadline,
            'type' => $request->type,
            'student_group_set_id' => $request->type === 'group' ? $request->student_group_set_id : null,
            'marking_mode' => $request->marking_mode,
            'submission_type' => $request->submission_type,
            ...$schemeData,
        ]);

        return redirect()
            ->route('tenant.assignments.show', [$tenant->slug, $assignment->id])
            ->with('success', 'Assignment updated.');
    }

    private function validateGroupSetBelongsToCourse(Request $request): void
    {
        if ($request->type !== 'group' || ! $request->filled('student_group_set_id')) {
            return;
        }

        $valid = StudentGroupSet::where('id', $request->student_group_set_id)
            ->where('course_id', $request->course_id)
            ->exists();

        if (! $valid) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'student_group_set_id' => 'Selected group set does not belong to the chosen course.',
            ]);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $isSubAssignment = $request->filled('parent_id');

        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'parent_id' => ['nullable', 'exists:assignments,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'total_marks' => ['required', 'numeric', 'min:1'],
            'deadline' => ['nullable', 'date'],
            'type' => ['required', 'in:individual,group'],
            'student_group_set_id' => ['nullable', 'required_if:type,group', 'exists:student_group_sets,id'],
            'marking_mode' => ['required', 'in:manual,ai_assisted'],
            'submission_type' => ['required', 'in:file,text,both'],
            'answer_scheme' => ['nullable', 'string'],
            'answer_scheme_file' => ['nullable', 'file', 'max:25600', 'mimes:pdf'],
            'instruction_file' => ['nullable', 'file', 'max:25600', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'criteria' => ['nullable', 'array'],
            'criteria.*.title' => ['required_with:criteria', 'string'],
            'criteria.*.max_marks' => ['required_with:criteria', 'numeric', 'min:0'],
            'criteria.*.levels' => ['nullable', 'array'],
        ]);

        $this->validateGroupSetBelongsToCourse($request);

        $tenant = app('current_tenant');
        $lecturer = auth()->user();

        // If creating sub-assignment, validate parent exists and belongs to same course
        if ($isSubAssignment) {
            $parent = Assignment::findOrFail($request->parent_id);
            if ($parent->course_id != $request->course_id) {
                return back()->withErrors(['parent_id' => 'Sub-assignment must belong to the same course as parent.'])->withInput();
            }
        }

        // Resolve Drive service once if lecturer has Drive connected
        $driveService = null;
        $courseFolderId = null;
        if ($lecturer->isDriveConnected()) {
            try {
                $driveService = app(GoogleDriveService::class);
                $course = Course::find($request->course_id);
                if ($course) {
                    $courseFolderId = $driveService->findOrCreateFolder(
                        $lecturer,
                        "{$course->code} — {$course->title}"
                    );
                }
            } catch (\Throwable) {
                $driveService = null;
                $courseFolderId = null;
            }
        }

        $schemeData = [
            'answer_scheme' => $request->answer_scheme,
            'answer_scheme_path' => null,
            'answer_scheme_filename' => null,
            'answer_scheme_drive_file_id' => null,
        ];

        if ($request->hasFile('answer_scheme_file')) {
            $file = $request->file('answer_scheme_file');
            $schemeData['answer_scheme_path'] = $file->store('answer-schemes', 'local');
            $schemeData['answer_scheme_filename'] = $file->getClientOriginalName();

            if ($driveService && $courseFolderId) {
                try {
                    $schemeFolderId = $driveService->findOrCreateFolder($lecturer, 'Answer Schemes', $courseFolderId);
                    $result = $driveService->uploadFile(
                        $lecturer,
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                        $file->getClientMimeType(),
                        $schemeFolderId
                    );
                    $schemeData['answer_scheme_drive_file_id'] = $result['id'];
                } catch (\Throwable) {
                    // Drive upload failed — keep local copy only
                }
            }
        }

        $instructionData = [
            'instruction_file_path' => null,
            'instruction_filename' => null,
            'instruction_drive_file_id' => null,
            'instruction_drive_web_link' => null,
        ];

        if ($request->hasFile('instruction_file')) {
            $file = $request->file('instruction_file');
            $instructionData['instruction_file_path'] = $file->store('assignment-instructions', 'local');
            $instructionData['instruction_filename'] = $file->getClientOriginalName();

            if ($driveService && $courseFolderId) {
                try {
                    $instructionFolderId = $driveService->findOrCreateFolder($lecturer, 'Assignment Instructions', $courseFolderId);
                    $result = $driveService->uploadFile(
                        $lecturer,
                        $file->getRealPath(),
                        $file->getClientOriginalName(),
                        $file->getClientMimeType(),
                        $instructionFolderId
                    );
                    $instructionData['instruction_drive_file_id'] = $result['id'];
                    $instructionData['instruction_drive_web_link'] = $result['web_view_link'];
                } catch (\Throwable) {
                    // Drive upload failed — keep local copy only
                }
            }
        }

        $assignment = Assignment::create([
            'tenant_id' => $tenant->id,
            'course_id' => $request->course_id,
            'parent_id' => $request->parent_id,
            'student_group_set_id' => $request->type === 'group' ? $request->student_group_set_id : null,
            'created_by' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'total_marks' => $request->total_marks,
            'deadline' => $request->deadline,
            'type' => $request->type,
            'marking_mode' => $request->marking_mode,
            'submission_type' => $request->submission_type,
            'status' => 'draft',
            ...$schemeData,
            ...$instructionData,
        ]);

        // Create rubric with criteria
        if ($request->criteria && count($request->criteria) > 0) {
            $rubric = Rubric::create([
                'assignment_id' => $assignment->id,
                'type' => 'matrix',
            ]);

            foreach ($request->criteria as $i => $cData) {
                if (empty($cData['title'])) continue;

                $criteria = RubricCriteria::create([
                    'rubric_id' => $rubric->id,
                    'title' => $cData['title'],
                    'description' => $cData['description'] ?? null,
                    'max_marks' => $cData['max_marks'],
                    'sort_order' => $i,
                ]);

                if (! empty($cData['levels'])) {
                    foreach ($cData['levels'] as $j => $lData) {
                        if (empty($lData['label'])) continue;
                        RubricLevel::create([
                            'rubric_criteria_id' => $criteria->id,
                            'label' => $lData['label'],
                            'description' => $lData['description'] ?? null,
                            'marks' => $lData['marks'] ?? 0,
                            'sort_order' => $j,
                        ]);
                    }
                }
            }
        }

        $redirectRoute = $isSubAssignment
            ? route('tenant.assignments.show', [$tenant->slug, $assignment->parent_id])
            : route('tenant.assignments.show', [$tenant->slug, $assignment->id]);

        return redirect($redirectRoute)->with('success', $isSubAssignment ? 'Sub-assignment created.' : 'Assignment created.');
    }

    public function show(string $tenantSlug, Assignment $assignment): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');
        $role = $user->roleInTenant($tenant->id);

        $assignment->load([
            'course', 'rubric.criteria.levels', 'subAssignments', 'parent',
            'submissions.user', 'submissions.files',
            'submissions.assignmentGroup', 'submissions.studentGroup',
            'groups.members.user', 'studentGroupSet.groups.members.user',
        ]);

        if ($role === 'student') {
            $mySubmission = $assignment->submissions->where('user_id', $user->id)->first();
            $myMark = StudentMark::where('assignment_id', $assignment->id)->where('user_id', $user->id)->first();
            $myFeedback = $mySubmission ? Feedback::where('submission_id', $mySubmission->id)->where('user_id', $user->id)->where('is_released', true)->first() : null;

            // Group assignment context
            $myGroup = null;
            $isLeader = false;
            $groupSubmission = null;
            $groupLeader = null;
            $activeVoteRound = null;
            if ($assignment->isGroupAssignment()) {
                $myGroup = $assignment->groupForUser($user->id);
                if ($myGroup) {
                    $myGroup->load('members.user');
                    $isLeader = $assignment->isGroupLeader($user->id);

                    if ($assignment->usesStudentGroupSet() && $myGroup instanceof StudentGroup) {
                        $groupLeader = $myGroup->leader();
                        $activeVoteRound = $myGroup->activeVoteRound();
                        $groupSubmission = $assignment->submissions
                            ->where('student_group_id', $myGroup->id)
                            ->first();
                    } else {
                        $groupSubmission = $assignment->submissions
                            ->where('assignment_group_id', $myGroup->id)
                            ->first();
                    }
                }
            }

            return view('tenant.assignments.student-show', compact(
                'assignment', 'mySubmission', 'myMark', 'myFeedback',
                'myGroup', 'isLeader', 'groupSubmission', 'groupLeader', 'activeVoteRound'
            ));
        }

        // Load submission counts for sub-assignments
        $subAssignmentIds = $assignment->subAssignments->pluck('id');
        $subSubmissionCounts = Submission::whereIn('assignment_id', $subAssignmentIds)
            ->selectRaw('assignment_id, COUNT(*) as count')
            ->groupBy('assignment_id')
            ->pluck('count', 'assignment_id');

        return view('tenant.assignments.show', compact('assignment', 'subSubmissionCounts'));
    }

    public function publish(string $tenantSlug, Assignment $assignment): RedirectResponse
    {
        $assignment->update(['status' => 'published']);
        $assignment->load('course');

        // Notify enrolled students
        $sectionIds = $assignment->course->sections()->pluck('id');
        $students = \App\Models\SectionStudent::whereIn('section_id', $sectionIds)
            ->where('is_active', true)->with('user')->get();

        foreach ($students as $ss) {
            $ss->user->notify(new AssignmentPublished($assignment));
        }

        return back()->with('success', 'Assignment published. ' . $students->count() . ' students notified.');
    }

    /**
     * Student submits work.
     */
    public function submit(Request $request, string $tenantSlug, Assignment $assignment): RedirectResponse
    {
        $subType = $assignment->submission_type ?? 'file';

        $rules = [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($subType === 'file') {
            $rules['files'] = ['required', 'array', 'min:1'];
            $rules['files.*'] = ['file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx'];
        } elseif ($subType === 'text') {
            $rules['text_content'] = ['required', 'string'];
        } else {
            // both — at least one must be provided
            $rules['files'] = ['nullable', 'array'];
            $rules['files.*'] = ['file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx'];
            $rules['text_content'] = ['nullable', 'string'];
        }

        $request->validate($rules);

        // For "both" type, ensure at least one is provided
        if ($subType === 'both' && !$request->hasFile('files') && !$request->filled('text_content')) {
            return back()->withErrors(['submit' => 'Please provide either files or text content.'])->withInput();
        }

        $user = auth()->user();
        $isLate = $assignment->deadline && now()->isAfter($assignment->deadline);

        // For group assignments, only the leader can submit
        $assignmentGroupId = null;
        $studentGroupId = null;
        $myGroup = null;
        if ($assignment->isGroupAssignment()) {
            $myGroup = $assignment->groupForUser($user->id);
            if (! $myGroup) {
                return back()->withErrors(['submit' => 'You are not assigned to a group for this assignment.']);
            }
            if (! $assignment->isGroupLeader($user->id)) {
                $message = $assignment->usesStudentGroupSet()
                    ? 'Only the group leader can submit. Your group needs to hold a vote to elect a leader.'
                    : 'Only the group leader can submit for the group.';
                return back()->withErrors(['submit' => $message]);
            }

            if ($assignment->usesStudentGroupSet()) {
                $studentGroupId = $myGroup->id;
            } else {
                $assignmentGroupId = $myGroup->id;
            }
        }

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'assignment_group_id' => $assignmentGroupId,
            'student_group_id' => $studentGroupId,
            'notes' => $request->notes,
            'text_content' => $request->text_content,
            'is_late' => $isLate,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        if ($request->hasFile('files')) {
            // Check if the course lecturer has Google Drive connected
            $assignment->load('course');
            $lecturer = $assignment->course->lecturer;
            $driveFolderId = null;

            if ($lecturer && $lecturer->isDriveConnected()) {
                try {
                    $driveService = app(GoogleDriveService::class);
                    $courseFolderId = $driveService->findOrCreateFolder(
                        $lecturer,
                        "{$assignment->course->code} — {$assignment->course->title}"
                    );
                    $submissionsFolderId = $driveService->findOrCreateFolder($lecturer, 'Submissions', $courseFolderId);
                    $driveFolderId = $driveService->findOrCreateFolder($lecturer, $assignment->title, $submissionsFolderId);
                } catch (\Throwable) {
                    // Drive unavailable — continue with local storage only
                    $driveFolderId = null;
                }
            }

            foreach ($request->file('files') as $file) {
                $path = $file->store('submissions/' . $assignment->id, 'local');
                $driveFileId = null;

                if ($driveFolderId && $lecturer) {
                    try {
                        $result = app(GoogleDriveService::class)->uploadFile(
                            $lecturer,
                            $file->getRealPath(),
                            $user->name . ' — ' . $file->getClientOriginalName(),
                            $file->getClientMimeType(),
                            $driveFolderId
                        );
                        $driveFileId = $result['id'];
                    } catch (\Throwable) {
                        // Drive upload failed — keep local copy
                    }
                }

                SubmissionFile::create([
                    'submission_id' => $submission->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getClientMimeType(),
                    'file_size_bytes' => $file->getSize(),
                    'storage_path' => $path,
                    'drive_file_id' => $driveFileId,
                ]);
            }
        }

        // For group assignments, auto-create submissions for other group members
        if ($assignment->isGroupAssignment() && $myGroup) {
            $myGroup->loadMissing('members');
            foreach ($myGroup->members as $member) {
                if ((int) $member->user_id === (int) $user->id) continue; // Skip leader

                Submission::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $member->user_id,
                    'assignment_group_id' => $assignmentGroupId,
                    'student_group_id' => $studentGroupId,
                    'notes' => $request->notes,
                    'text_content' => $request->text_content,
                    'is_late' => $isLate,
                    'submitted_at' => now(),
                    'status' => 'submitted',
                ]);
            }
        }

        // Notify lecturer
        $assignment->load('course');
        $lecturer = $assignment->course->lecturer;
        if ($lecturer) {
            $lecturer->notify(new SubmissionReceived($assignment, $user));
        }

        $successMsg = $assignment->isGroupAssignment()
            ? 'Group submission uploaded successfully.' . ($isLate ? ' (Late submission)' : '')
            : 'Submission uploaded successfully.' . ($isLate ? ' (Late submission)' : '');

        return back()->with('success', $successMsg);
    }

    /**
     * Lecturer views a submission for marking.
     */
    public function review(string $tenantSlug, Assignment $assignment, Submission $submission): View
    {
        $assignment->load(['rubric.criteria.levels', 'course', 'groups.members.user', 'studentGroupSet']);
        $submission->load(['user', 'files', 'markingSuggestions', 'assignmentGroup.members.user', 'studentGroup.members.user']);

        $existingMark = StudentMark::where('assignment_id', $assignment->id)
            ->where('user_id', $submission->user_id)->first();

        $groupMembers = null;
        if ($assignment->isGroupAssignment()) {
            if ($submission->student_group_id) {
                $groupMembers = StudentGroupMember::where('student_group_id', $submission->student_group_id)
                    ->with('user')
                    ->get();
            } elseif ($submission->assignment_group_id) {
                $groupMembers = AssignmentGroupMember::where('assignment_group_id', $submission->assignment_group_id)
                    ->with('user')
                    ->get();
            }
        }

        return view('tenant.assignments.review', compact('assignment', 'submission', 'existingMark', 'groupMembers'));
    }

    /**
     * Get student group sets available for a course (for group assignment builder).
     */
    public function courseGroupSets(string $tenantSlug, Course $course): JsonResponse
    {
        $this->authorizeCourseAccess($course);

        $sets = $course->studentGroupSets()
            ->with(['groups' => fn ($q) => $q->withCount('members')])
            ->where('is_active', true)
            ->latest()
            ->get()
            ->map(fn ($set) => [
                'id' => $set->id,
                'name' => $set->name,
                'type' => $set->type,
                'description' => $set->description,
                'groups_count' => $set->groups->count(),
                'total_members' => $set->groups->sum('members_count'),
                'groups' => $set->groups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'members_count' => $g->members_count,
                ])->values(),
            ]);

        return response()->json($sets);
    }

    /**
     * Get students enrolled in a course (for group builder).
     */
    public function courseStudents(string $tenantSlug, Course $course): JsonResponse
    {
        $sectionIds = $course->sections()->pluck('id');
        $students = SectionStudent::whereIn('section_id', $sectionIds)
            ->where('is_active', true)
            ->with('user:id,name,email')
            ->get()
            ->pluck('user')
            ->unique('id')
            ->values()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return response()->json($students);
    }

    /**
     * Lecturer finalizes marks for a submission.
     */
    public function finalizeMark(Request $request, string $tenantSlug, Assignment $assignment, Submission $submission): RedirectResponse
    {
        $request->validate([
            'marks' => ['required', 'array'],
            'marks.*' => ['required', 'numeric', 'min:0'],
            'feedback_strengths' => ['nullable', 'string'],
            'feedback_improvements' => ['nullable', 'string'],
        ]);

        $tenant = app('current_tenant');
        $totalMarks = array_sum($request->marks);
        $maxMarks = (float) $assignment->total_marks;
        $percentage = $maxMarks > 0 ? round($totalMarks / $maxMarks * 100, 2) : 0;

        // Determine all users to receive marks
        $usersToMark = collect();
        if ($assignment->isGroupAssignment() && $submission->student_group_id) {
            $groupSubmissions = Submission::where('assignment_id', $assignment->id)
                ->where('student_group_id', $submission->student_group_id)
                ->get();
            $usersToMark = $groupSubmissions->map(fn ($s) => ['user_id' => $s->user_id, 'submission' => $s]);
        } elseif ($assignment->isGroupAssignment() && $submission->assignment_group_id) {
            $groupSubmissions = Submission::where('assignment_id', $assignment->id)
                ->where('assignment_group_id', $submission->assignment_group_id)
                ->get();
            $usersToMark = $groupSubmissions->map(fn ($s) => ['user_id' => $s->user_id, 'submission' => $s]);
        } else {
            $usersToMark = collect([['user_id' => $submission->user_id, 'submission' => $submission]]);
        }

        foreach ($usersToMark as $entry) {
            StudentMark::updateOrCreate(
                ['assignment_id' => $assignment->id, 'user_id' => $entry['user_id']],
                [
                    'tenant_id' => $tenant->id,
                    'submission_id' => $entry['submission']->id,
                    'total_marks' => $totalMarks,
                    'max_marks' => $maxMarks,
                    'percentage' => $percentage,
                    'is_final' => true,
                    'finalized_by' => auth()->id(),
                    'finalized_at' => now(),
                ]
            );

            $entry['submission']->update(['status' => 'graded']);

            // Save feedback for each member
            if ($request->feedback_strengths || $request->feedback_improvements) {
                Feedback::updateOrCreate(
                    ['submission_id' => $entry['submission']->id, 'user_id' => $entry['user_id']],
                    [
                        'strengths' => $request->feedback_strengths,
                        'improvement_tips' => $request->feedback_improvements,
                        'performance_level' => $percentage >= 70 ? 'advanced' : ($percentage >= 40 ? 'average' : 'low'),
                        'is_released' => true,
                        'released_at' => now(),
                    ]
                );
            }

            // Notify each student
            $mark = StudentMark::where('assignment_id', $assignment->id)->where('user_id', $entry['user_id'])->first();
            if ($mark) {
                $entry['submission']->user->notify(new FeedbackReleased($assignment, $mark));
            }
        }

        $successMsg = $assignment->isGroupAssignment()
            ? "Marks finalized for group ({$usersToMark->count()} members)."
            : "Marks finalized for {$submission->user->name}.";

        return redirect()->route('tenant.assignments.show', [
            'tenant' => $tenant->slug,
            'assignment' => $assignment->id,
        ])->with('success', $successMsg);
    }

    /**
     * Serve the assignment instruction file for download.
     */
    public function downloadInstruction(string $tenantSlug, Assignment $assignment): Response|RedirectResponse
    {
        if (! $assignment->instruction_file_path && ! $assignment->instruction_drive_web_link) {
            abort(404);
        }

        // If Drive link available, redirect to it
        if ($assignment->instruction_drive_web_link) {
            return redirect($assignment->instruction_drive_web_link);
        }

        // Serve from local storage
        if (! Storage::disk('local')->exists($assignment->instruction_file_path)) {
            abort(404);
        }

        return response(
            Storage::disk('local')->get($assignment->instruction_file_path),
            200,
            [
                'Content-Type' => Storage::disk('local')->mimeType($assignment->instruction_file_path),
                'Content-Disposition' => 'inline; filename="' . $assignment->instruction_filename . '"',
            ]
        );
    }

    public function destroy(string $tenantSlug, Assignment $assignment): RedirectResponse
    {
        $tenant = app('current_tenant');

        // If this is a parent assignment, also delete all sub-assignments
        if ($assignment->isParent()) {
            foreach ($assignment->subAssignments as $sub) {
                $this->deleteAssignmentSubmissions($sub);
                $sub->delete();
            }
        }

        $this->deleteAssignmentSubmissions($assignment);
        $assignment->delete();

        $redirectRoute = $assignment->parent_id
            ? route('tenant.assignments.show', [$tenant->slug, $assignment->parent_id])
            : route('tenant.assignments.index', $tenant->slug);

        return redirect($redirectRoute)
            ->with('success', "Assignment \"{$assignment->title}\" deleted.");
    }

    private function deleteAssignmentSubmissions(Assignment $assignment): void
    {
        // Clean up local submission files
        foreach ($assignment->submissions as $submission) {
            foreach ($submission->files as $file) {
                if ($file->storage_path && Storage::disk('local')->exists($file->storage_path)) {
                    Storage::disk('local')->delete($file->storage_path);
                }
            }
        }

        // Clean up answer scheme file
        if ($assignment->answer_scheme_path && Storage::disk('local')->exists($assignment->answer_scheme_path)) {
            Storage::disk('local')->delete($assignment->answer_scheme_path);
        }

        // Clean up instruction file
        if ($assignment->instruction_file_path && Storage::disk('local')->exists($assignment->instruction_file_path)) {
            Storage::disk('local')->delete($assignment->instruction_file_path);
        }
    }

    /**
     * Trigger AI marking (stub — generates mock suggestions).
     */
    public function aiMark(string $tenantSlug, Assignment $assignment, Submission $submission): RedirectResponse
    {
        $assignment->load('rubric.criteria');
        $submission->update(['status' => 'ai_processing']);

        // Generate mock AI suggestions per rubric criteria
        if ($assignment->rubric) {
            foreach ($assignment->rubric->criteria as $criteria) {
                $suggestedPct = rand(50, 95) / 100;
                $suggestedMarks = round((float) $criteria->max_marks * $suggestedPct, 2);

                MarkingSuggestion::updateOrCreate(
                    ['submission_id' => $submission->id, 'rubric_criteria_id' => $criteria->id],
                    [
                        'suggested_marks' => $suggestedMarks,
                        'max_marks' => $criteria->max_marks,
                        'explanation' => "AI analysis suggests {$suggestedMarks}/{$criteria->max_marks} based on content relevance and completeness.",
                        'confidence' => round($suggestedPct, 2),
                        'status' => 'pending',
                    ]
                );
            }
        }

        $submission->update(['status' => 'ai_completed']);

        return back()->with('success', 'AI marking suggestions generated.');
    }
}
