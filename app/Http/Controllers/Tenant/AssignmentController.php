<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Feedback;
use App\Models\MarkingSuggestion;
use App\Models\Rubric;
use App\Models\RubricCriteria;
use App\Models\RubricLevel;
use App\Models\Section;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $role = $user->roleInTenant(app('current_tenant')->id);

        if ($role === 'student') {
            // Student: show assignments from enrolled courses
            $sectionIds = $user->sections()->pluck('sections.id');
            $courseIds = Section::whereIn('id', $sectionIds)->pluck('course_id')->unique();
            $assignments = Assignment::whereIn('course_id', $courseIds)
                ->where('status', 'published')
                ->with('course')
                ->latest('deadline')
                ->get();

            return view('tenant.assignments.student-index', compact('assignments'));
        }

        // Lecturer
        $courseIds = Course::where('lecturer_id', $user->id)->pluck('id');
        $assignments = Assignment::whereIn('course_id', $courseIds)
            ->withCount('submissions')
            ->with('course')
            ->latest()
            ->get();

        return view('tenant.assignments.index', compact('assignments'));
    }

    public function create(): View
    {
        $courses = Course::where('lecturer_id', auth()->id())->with('sections', 'learningOutcomes')->get();
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
            'answer_scheme' => ['nullable', 'string'],
            'criteria' => ['nullable', 'array'],
            'criteria.*.title' => ['required_with:criteria', 'string'],
            'criteria.*.max_marks' => ['required_with:criteria', 'numeric', 'min:0'],
            'criteria.*.levels' => ['nullable', 'array'],
        ]);

        $tenant = app('current_tenant');

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
            'answer_scheme' => $request->answer_scheme,
            'status' => 'draft',
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
        return back()->with('success', 'Assignment published. Students can now submit.');
    }

    /**
     * Student submits work.
     */
    public function submit(Request $request, string $tenantSlug, Assignment $assignment): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:25600', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth()->user();
        $isLate = $assignment->deadline && now()->isAfter($assignment->deadline);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'notes' => $request->notes,
            'is_late' => $isLate,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('submissions/' . $assignment->id, 'local');

            SubmissionFile::create([
                'submission_id' => $submission->id,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'storage_path' => $path,
            ]);
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

        return redirect()->route('tenant.assignments.show', [
            'tenant' => $tenant->slug,
            'assignment' => $assignment->id,
        ])->with('success', "Marks finalized for {$submission->user->name}.");
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
