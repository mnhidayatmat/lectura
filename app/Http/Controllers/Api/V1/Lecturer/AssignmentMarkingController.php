<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Feedback;
use App\Models\RubricCriteria;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Notifications\FeedbackReleased;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assignment marking for the mobile app — the web's AssignmentController review and
 * finalizeMark, with the authorization and mark limits the web leaves out.
 */
class AssignmentMarkingController extends Controller
{
    use AuthorizesLecturerAccess;

    public function index(Request $request): JsonResponse
    {
        $this->ensureLecturer();

        $request->validate(['course_id' => ['nullable', 'integer']]);

        $assignments = Assignment::query()
            ->whereIn('course_id', $this->accessibleCourseIds())
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->with(['course', 'submissions:id,assignment_id,user_id,student_group_id,assignment_group_id,status'])
            ->withCount('subAssignments')
            ->orderByRaw('deadline is null')
            ->orderByDesc('deadline')
            ->latest()
            ->get();

        return response()->json([
            'data' => $assignments->map(fn (Assignment $assignment) => $this->summary($assignment))->values(),
        ]);
    }

    public function show(Assignment $assignment): JsonResponse
    {
        $this->authorizeAssignment($assignment);

        $assignment->load([
            'course',
            'rubric.criteria.levels',
            'submissions' => fn ($q) => $q->with(['user', 'studentGroup', 'assignmentGroup'])->withCount('files')->latest('submitted_at'),
        ]);

        $marks = StudentMark::where('assignment_id', $assignment->id)->get()->keyBy('user_id');
        $studentIds = $this->studentIdNumbers($assignment->submissions->pluck('user_id'));

        return response()->json([
            'data' => array_merge($this->summary($assignment), [
                'description' => $assignment->description,
                'rubric' => $this->rubric($assignment),
                'submissions' => $this->markingUnits($assignment->submissions, $assignment)
                    ->map(fn (Submission $submission) => $this->submissionRow($submission, $marks->get($submission->user_id), $studentIds[$submission->user_id] ?? null))
                    ->values(),
            ]),
        ]);
    }

    public function submission(Assignment $assignment, Submission $submission): JsonResponse
    {
        $this->authorizeSubmission($assignment, $submission);

        $submission->load(['user', 'files', 'feedback', 'markingSuggestions', 'studentGroup', 'assignmentGroup']);
        $assignment->load(['course', 'rubric.criteria.levels', 'submissions'])->loadCount('subAssignments');

        $mark = StudentMark::where('assignment_id', $assignment->id)->where('user_id', $submission->user_id)->first();

        return response()->json([
            'data' => array_merge($this->submissionRow($submission, $mark, $this->studentIdNumbers([$submission->user_id])[$submission->user_id] ?? null), [
                'notes' => $submission->notes,
                'text_content' => $submission->text_content,
                'files' => $submission->files->map(fn (SubmissionFile $file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'mime_type' => $file->file_type,
                    'size_bytes' => $file->file_size_bytes ? (int) $file->file_size_bytes : null,
                    'has_annotations' => $file->annotated_at !== null,
                ])->values(),
                'feedback' => $submission->feedback ? [
                    'strengths' => $submission->feedback->strengths,
                    'improvements' => $submission->feedback->improvement_tips,
                    'is_released' => (bool) $submission->feedback->is_released,
                ] : null,
                'suggestions' => $submission->markingSuggestions->map(fn ($s) => [
                    'rubric_criteria_id' => $s->rubric_criteria_id,
                    'suggested_marks' => $s->suggested_marks !== null ? (float) $s->suggested_marks : null,
                    'max_marks' => $s->max_marks !== null ? (float) $s->max_marks : null,
                    'explanation' => $s->explanation,
                ])->values(),
                'group_members' => $this->groupSubmissions($assignment, $submission)
                    ->map(fn (Submission $member) => ['id' => $member->user_id, 'name' => $member->user?->name])
                    ->values(),
                'assignment' => array_merge($this->summary($assignment), [
                    'rubric' => $this->rubric($assignment),
                ]),
            ]),
        ]);
    }

    public function file(Assignment $assignment, Submission $submission, SubmissionFile $file): StreamedResponse
    {
        $this->authorizeSubmission($assignment, $submission);

        if ($file->submission_id !== $submission->id) {
            abort(404, 'File not found.');
        }

        if (! $file->storage_path || ! Storage::disk('local')->exists($file->storage_path)) {
            abort(404, 'This file is not stored on Lectura. Open it on the web.');
        }

        return Storage::disk('local')->download($file->storage_path, $file->file_name);
    }

    /**
     * Save the marks and release them with the feedback, as finalizeMark does on the web:
     * one step, applied to every member of a group.
     */
    public function mark(Request $request, Assignment $assignment, Submission $submission): JsonResponse
    {
        $this->authorizeSubmission($assignment, $submission);

        $assignment->load('rubric.criteria');
        $criteria = $assignment->rubric?->criteria ?? collect();
        $maxMarks = (float) $assignment->total_marks;

        $rules = [
            'feedback_strengths' => ['nullable', 'string', 'max:5000'],
            'feedback_improvements' => ['nullable', 'string', 'max:5000'],
        ];

        if ($criteria->isNotEmpty()) {
            $rules['criteria'] = ['required', 'array'];
            foreach ($criteria as $criterion) {
                $rules["criteria.{$criterion->id}"] = ['required', 'numeric', 'min:0', 'max:'.(float) $criterion->max_marks];
            }
        } else {
            $rules['total'] = ['required', 'numeric', 'min:0', 'max:'.$maxMarks];
        }

        $request->validate($rules);

        $total = $criteria->isNotEmpty()
            ? round((float) $criteria->sum(fn (RubricCriteria $criterion) => (float) $request->input("criteria.{$criterion->id}")), 2)
            : round((float) $request->input('total'), 2);

        if ($total > $maxMarks) {
            throw ValidationException::withMessages([
                'criteria' => "The criteria add up to {$total}, more than the assignment's {$maxMarks} marks.",
            ]);
        }

        $percentage = $maxMarks > 0 ? round($total / $maxMarks * 100, 2) : 0;
        $strengths = $request->input('feedback_strengths');
        $improvements = $request->input('feedback_improvements');
        $targets = $this->groupSubmissions($assignment, $submission);

        foreach ($targets as $target) {
            $mark = StudentMark::updateOrCreate(
                ['assignment_id' => $assignment->id, 'user_id' => $target->user_id],
                [
                    'tenant_id' => app('current_tenant')->id,
                    'submission_id' => $target->id,
                    'total_marks' => $total,
                    'max_marks' => $maxMarks,
                    'percentage' => $percentage,
                    'is_final' => true,
                    'finalized_by' => $request->user()->id,
                    'finalized_at' => now(),
                ]
            );

            $target->update(['status' => 'graded']);

            if ($strengths || $improvements) {
                Feedback::updateOrCreate(
                    ['submission_id' => $target->id, 'user_id' => $target->user_id],
                    [
                        'strengths' => $strengths,
                        'improvement_tips' => $improvements,
                        'performance_level' => $percentage >= 70 ? 'advanced' : ($percentage >= 40 ? 'average' : 'low'),
                        'is_released' => true,
                        'released_at' => now(),
                    ]
                );
            }

            $target->user?->notify(new FeedbackReleased($assignment, $mark));
        }

        return response()->json([
            'message' => $targets->count() > 1
                ? "Marks released to the group ({$targets->count()} members)."
                : "Marks released to {$submission->user?->name}.",
            'data' => [
                'total_marks' => $total,
                'max_marks' => $maxMarks,
                'percentage' => (float) $percentage,
                'members_marked' => $targets->count(),
            ],
        ]);
    }

    private function authorizeAssignment(Assignment $assignment): void
    {
        $this->ensureLecturer();

        if (! $assignment->course) {
            abort(404, 'Assignment not found.');
        }

        $this->authorizeCourse($assignment->course);
    }

    private function authorizeSubmission(Assignment $assignment, Submission $submission): void
    {
        $this->authorizeAssignment($assignment);

        if ($submission->assignment_id !== $assignment->id) {
            abort(404, 'Submission not found.');
        }
    }

    /**
     * Every submission that shares the marks: the group's copies, or just this one.
     *
     * @return Collection<int, Submission>
     */
    private function groupSubmissions(Assignment $assignment, Submission $submission): Collection
    {
        $key = $this->groupKey($submission);

        if (! $assignment->isGroupAssignment() || $key === null) {
            return collect([$submission]);
        }

        return Submission::where('assignment_id', $assignment->id)
            ->where($key, $submission->{$key})
            ->with('user')
            ->get();
    }

    private function groupKey(Submission $submission): ?string
    {
        return match (true) {
            $submission->student_group_id !== null => 'student_group_id',
            $submission->assignment_group_id !== null => 'assignment_group_id',
            default => null,
        };
    }

    /**
     * One row per thing to mark. A group submits once and every member gets a copy
     * without the files, so the copy carrying the files stands for the group.
     *
     * @param  Collection<int, Submission>  $submissions
     * @return Collection<int, Submission>
     */
    private function markingUnits(Collection $submissions, Assignment $assignment): Collection
    {
        if (! $assignment->isGroupAssignment()) {
            return $submissions;
        }

        return $submissions
            ->groupBy(fn (Submission $s) => ($key = $this->groupKey($s)) ? $key.':'.$s->{$key} : 'user:'.$s->user_id)
            ->map(fn (Collection $copies) => $copies->sortByDesc(fn (Submission $s) => [(int) ($s->files_count ?? 0), -$s->id])->first())
            ->sortByDesc(fn (Submission $s) => $s->submitted_at?->getTimestamp() ?? 0)
            ->values();
    }

    private function summary(Assignment $assignment): array
    {
        $units = $this->markingUnits($assignment->submissions, $assignment);

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'type' => $assignment->type,
            'status' => $assignment->status,
            'total_marks' => (float) $assignment->total_marks,
            'deadline' => $assignment->deadline?->toIso8601String(),
            'marking_mode' => $assignment->marking_mode,
            'submission_type' => $assignment->submission_type,
            'parent_id' => $assignment->parent_id,
            'sub_assignments_count' => (int) ($assignment->sub_assignments_count ?? 0),
            'course' => $assignment->course ? ['id' => $assignment->course->id, 'code' => $assignment->course->code, 'title' => $assignment->course->title] : null,
            'counts' => [
                'submissions' => $units->count(),
                'graded' => $units->where('status', 'graded')->count(),
            ],
        ];
    }

    private function rubric(Assignment $assignment): ?array
    {
        $criteria = $assignment->rubric?->criteria;

        if (! $criteria || $criteria->isEmpty()) {
            return null;
        }

        return [
            'criteria' => $criteria->map(fn (RubricCriteria $criterion) => [
                'id' => $criterion->id,
                'title' => $criterion->title,
                'description' => $criterion->description,
                'max_marks' => (float) $criterion->max_marks,
                'levels' => $criterion->levels->map(fn ($level) => [
                    'label' => $level->label,
                    'description' => $level->description,
                    'marks' => (float) $level->marks,
                ])->values(),
            ])->values(),
        ];
    }

    private function submissionRow(Submission $submission, ?StudentMark $mark, ?string $studentIdNumber): array
    {
        $group = $submission->studentGroup ?? $submission->assignmentGroup;

        return [
            'id' => $submission->id,
            'status' => $submission->status,
            'is_late' => (bool) $submission->is_late,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'files_count' => (int) ($submission->files_count ?? $submission->files?->count() ?? 0),
            'student' => [
                'id' => $submission->user?->id,
                'name' => $submission->user?->name,
                'student_id_number' => $studentIdNumber,
            ],
            'group' => $group ? ['id' => $group->id, 'name' => $group->name] : null,
            'mark' => $mark ? [
                'total_marks' => (float) $mark->total_marks,
                'max_marks' => (float) $mark->max_marks,
                'percentage' => $mark->percentage !== null ? (float) $mark->percentage : null,
                'is_final' => (bool) $mark->is_final,
                'finalized_at' => $mark->finalized_at?->toIso8601String(),
            ] : null,
        ];
    }
}
