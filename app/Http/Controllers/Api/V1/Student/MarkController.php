<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Api\V1\Student\Concerns\InteractsWithEnrollments;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\StudentPresenter;
use App\Models\AssessmentScore;
use App\Models\Assignment;
use App\Models\StudentMark;
use App\Models\Submission;
use App\Models\SubmissionFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarkController extends Controller
{
    use InteractsWithEnrollments;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'filter' => ['nullable', 'in:all,graded,pending'],
        ]);

        $filter = $request->input('filter', 'all');
        $user = $request->user();
        $courseIds = $this->enrolledCourseIds($user);

        $assignments = Assignment::whereIn('course_id', $courseIds)
            ->whereIn('status', ['published', 'closed', 'marking', 'completed'])
            ->with('course')
            ->latest('deadline')
            ->get();

        $marks = StudentMark::where('user_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $submissions = Submission::where('user_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->with('feedback')
            ->get()
            ->keyBy('assignment_id');

        $gradedMarks = $marks->where('is_final', true);
        $average = $gradedMarks->avg('percentage');

        $items = $assignments
            ->map(function (Assignment $assignment) use ($marks, $submissions) {
                $mark = $marks->get($assignment->id);
                $submission = $submissions->get($assignment->id);
                $isGraded = $mark && $mark->is_final;
                $feedback = $submission?->feedback;

                return [
                    'assignment' => [
                        'id' => $assignment->id,
                        'title' => $assignment->title,
                        'type' => $assignment->type,
                        'total_marks' => (float) $assignment->total_marks,
                        'deadline' => $assignment->deadline?->toIso8601String(),
                    ],
                    'course' => StudentPresenter::course($assignment->course),
                    'status' => $isGraded ? 'graded' : ($submission ? 'submitted' : 'not_submitted'),
                    'is_late' => (bool) $submission?->is_late,
                    'submitted_at' => $submission?->submitted_at?->toIso8601String(),
                    'mark' => $isGraded ? StudentPresenter::mark($mark) : null,
                    'feedback' => $feedback && $feedback->is_released ? StudentPresenter::feedback($feedback) : null,
                ];
            })
            ->filter(fn (array $item) => match ($filter) {
                'graded' => $item['status'] === 'graded',
                'pending' => $item['status'] !== 'graded',
                default => true,
            })
            ->values();

        $assessmentScores = $filter === 'pending'
            ? collect()
            : AssessmentScore::where('user_id', $user->id)
                ->where('is_released', true)
                ->whereHas('assessment', fn ($q) => $q->whereIn('course_id', $courseIds))
                ->with('assessment.course')
                ->latest('released_at')
                ->get();

        return response()->json([
            'data' => [
                'filter' => $filter,
                'stats' => [
                    'total_assignments' => $assignments->count(),
                    'graded_count' => $gradedMarks->count(),
                    'pending_count' => $assignments->count() - $gradedMarks->count(),
                    'average_percentage' => $average !== null ? round((float) $average, 1) : null,
                ],
                'assignments' => $items,
                'assessment_scores' => $assessmentScores
                    ->map(fn (AssessmentScore $score) => StudentPresenter::assessmentScore($score))
                    ->values(),
            ],
        ]);
    }

    public function show(Request $request, StudentMark $mark): JsonResponse
    {
        if ($mark->user_id !== $request->user()->id) {
            abort(403, 'These marks do not belong to you.');
        }

        if (! $mark->is_final) {
            abort(403, 'These marks have not been released yet.');
        }

        $mark->load(['assignment.course', 'submission.feedback', 'submission.files']);

        $assignment = $mark->assignment;
        $submission = $mark->submission;
        $feedback = $submission?->feedback;

        return response()->json([
            'data' => [
                'mark' => StudentPresenter::mark($mark),
                'assignment' => $assignment ? [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'type' => $assignment->type,
                    'total_marks' => (float) $assignment->total_marks,
                    'deadline' => $assignment->deadline?->toIso8601String(),
                ] : null,
                'course' => StudentPresenter::course($assignment?->course),
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'is_late' => (bool) $submission->is_late,
                    'files' => $submission->files->map(fn (SubmissionFile $file) => [
                        'id' => $file->id,
                        'file_name' => $file->file_name,
                        'file_type' => $file->file_type,
                        'size_bytes' => (int) $file->file_size_bytes,
                    ])->values(),
                ] : null,
                'feedback' => $feedback && $feedback->is_released ? StudentPresenter::feedback($feedback) : null,
            ],
        ]);
    }

    public function downloadAnswerScript(Request $request, AssessmentScore $score): mixed
    {
        $tenant = app('current_tenant');
        $hasScript = $score->answer_script_drive_link || $score->answer_script_path;

        if ($score->user_id !== $request->user()->id || ! $score->is_released || ! $hasScript) {
            abort(404);
        }

        $score->loadMissing('assessment.course');

        if (! $score->assessment || $score->assessment->course?->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($score->answer_script_drive_link) {
            return redirect()->away($score->answer_script_drive_link);
        }

        if (! Storage::disk('local')->exists($score->answer_script_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $score->answer_script_path,
            $score->answer_script_filename ?? 'answer-script.pdf'
        );
    }
}
