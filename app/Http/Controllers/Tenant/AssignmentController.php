<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use App\Services\GoogleDriveService;
use App\Models\Feedback;
use App\Models\MarkingSuggestion;
use App\Models\Rubric;
use App\Models\RubricCriteria;
use App\Models\RubricLevel;
use App\Models\Section;
use App\Notifications\AssignmentPublished;
use App\Notifications\FeedbackReleased;
use App\Notifications\SubmissionReceived;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $sectionIds = $user->sections()->pluck('sections.id');
            $courseIds = Section::whereIn('id', $sectionIds)->pluck('course_id')->unique();
            $assignments = Assignment::whereIn('course_id', $courseIds)
                ->where('status', 'published')
                ->with('course')
                ->latest('deadline')
                ->get();

            $assignmentsByCourse = $assignments
                ->groupBy('course_id')
                ->map(fn ($items) => [
                    'course' => $items->first()->course,
                    'assignments' => $items,
                ])
                ->sortBy(fn ($group) => $group['course']->code);

            return view('tenant.assignments.student-index', compact('assignmentsByCourse'));
        }

        // Lecturer
        $courses = Course::whereIn('id', $this->accessibleCourseIds())->get();
        $assignments = Assignment::whereIn('course_id', $courses->pluck('id'))
            ->withCount('submissions')
            ->with('course')
            ->latest()
            ->get();

        return view('tenant.assignments.index', compact('assignments', 'courses'));
    }

    public function create(): View
    {
        $courses = Course::whereIn('id', $this->accessibleCourseIds())->with('sections', 'learningOutcomes')->get();
        return view('tenant.assignments.create', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'total_marks' => ['required', 'numeric', 'min:1'],
            'deadline' => ['nullable', 'date'],
            'type' => ['required', 'in:individual,group'],
            'marking_mode' => ['required', 'in:manual,ai_assisted'],
            'submission_type' => ['required', 'in:file,text,both'],
            'answer_scheme' => ['nullable', 'string'],
            'answer_scheme_file' => ['nullable', 'file', 'max:25600', 'mimes:pdf'],
            'criteria' => ['nullable', 'array'],
            'criteria.*.title' => ['required_with:criteria', 'string'],
            'criteria.*.max_marks' => ['required_with:criteria', 'numeric', 'min:0'],
            'criteria.*.levels' => ['nullable', 'array'],
        ]);

        $tenant = app('current_tenant');

        $schemeData = [
            'answer_scheme' => $request->answer_scheme,
            'answer_scheme_path' => null,
            'answer_scheme_filename' => null,
        ];

        if ($request->hasFile('answer_scheme_file')) {
            $file = $request->file('answer_scheme_file');
            $schemeData['answer_scheme_path'] = $file->store('answer-schemes', 'local');
            $schemeData['answer_scheme_filename'] = $file->getClientOriginalName();
        }

        $assignment = Assignment::create([
            'tenant_id' => $tenant->id,
            'course_id' => $request->course_id,
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

        return redirect()->route('tenant.assignments.show', [
            'tenant' => $tenant->slug,
            'assignment' => $assignment->id,
        ])->with('success', 'Assignment created.');
    }

    public function show(string $tenantSlug, Assignment $assignment): View
    {
        $user = auth()->user();
        $tenant = app('current_tenant');
        $role = $user->roleInTenant($tenant->id);

        $assignment->load(['course', 'rubric.criteria.levels', 'submissions.user', 'submissions.files']);

        if ($role === 'student') {
            $mySubmission = $assignment->submissions->where('user_id', $user->id)->first();
            $myMark = StudentMark::where('assignment_id', $assignment->id)->where('user_id', $user->id)->first();
            $myFeedback = $mySubmission ? Feedback::where('submission_id', $mySubmission->id)->where('user_id', $user->id)->where('is_released', true)->first() : null;

            return view('tenant.assignments.student-show', compact('assignment', 'mySubmission', 'myMark', 'myFeedback'));
        }

        return view('tenant.assignments.show', compact('assignment'));
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

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
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

        // Notify lecturer
        $assignment->load('course');
        $lecturer = $assignment->course->lecturer;
        if ($lecturer) {
            $lecturer->notify(new SubmissionReceived($assignment, $user));
        }

        return back()->with('success', 'Submission uploaded successfully.' . ($isLate ? ' (Late submission)' : ''));
    }

    /**
     * Lecturer views a submission for marking.
     */
    public function review(string $tenantSlug, Assignment $assignment, Submission $submission): View
    {
        $assignment->load(['rubric.criteria.levels', 'course']);
        $submission->load(['user', 'files', 'markingSuggestions']);

        $existingMark = StudentMark::where('assignment_id', $assignment->id)
            ->where('user_id', $submission->user_id)->first();

        return view('tenant.assignments.review', compact('assignment', 'submission', 'existingMark'));
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

        // Save or update student mark
        StudentMark::updateOrCreate(
            ['assignment_id' => $assignment->id, 'user_id' => $submission->user_id],
            [
                'tenant_id' => $tenant->id,
                'submission_id' => $submission->id,
                'total_marks' => $totalMarks,
                'max_marks' => $maxMarks,
                'percentage' => $percentage,
                'is_final' => true,
                'finalized_by' => auth()->id(),
                'finalized_at' => now(),
            ]
        );

        $submission->update(['status' => 'graded']);

        // Save feedback
        if ($request->feedback_strengths || $request->feedback_improvements) {
            Feedback::updateOrCreate(
                ['submission_id' => $submission->id, 'user_id' => $submission->user_id],
                [
                    'strengths' => $request->feedback_strengths,
                    'improvement_tips' => $request->feedback_improvements,
                    'performance_level' => $percentage >= 70 ? 'advanced' : ($percentage >= 40 ? 'average' : 'low'),
                    'is_released' => true,
                    'released_at' => now(),
                ]
            );
        }

        // Notify student
        $mark = StudentMark::where('assignment_id', $assignment->id)->where('user_id', $submission->user_id)->first();
        if ($mark) {
            $submission->user->notify(new FeedbackReleased($assignment, $mark));
        }

        return redirect()->route('tenant.assignments.show', [
            'tenant' => $tenant->slug,
            'assignment' => $assignment->id,
        ])->with('success', "Marks finalized for {$submission->user->name}.");
    }

    public function destroy(string $tenantSlug, Assignment $assignment): RedirectResponse
    {
        $tenant = app('current_tenant');

        // Clean up local submission files
        foreach ($assignment->submissions as $submission) {
            foreach ($submission->files as $file) {
                if ($file->storage_path && \Storage::disk('local')->exists($file->storage_path)) {
                    \Storage::disk('local')->delete($file->storage_path);
                }
            }
        }

        // Clean up answer scheme file
        if ($assignment->answer_scheme_path && \Storage::disk('local')->exists($assignment->answer_scheme_path)) {
            \Storage::disk('local')->delete($assignment->answer_scheme_path);
        }

        $assignment->delete();

        return redirect()->route('tenant.assignments.index', $tenant->slug)
            ->with('success', "Assignment \"{$assignment->title}\" deleted.");
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
