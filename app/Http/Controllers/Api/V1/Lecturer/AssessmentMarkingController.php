<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AssessmentSubmission;
use App\Models\AssessmentSubmissionFile;
use App\Models\Course;
use App\Models\RubricCriteria;
use App\Models\SectionStudent;
use App\Models\StudentGroupMember;
use App\Models\User;
use App\Notifications\AssessmentMarksReleased;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Assessment marking for the mobile app: the roster with scores, per-student marks and
 * release. Mirrors the web's AssessmentScoreController / AssessmentSubmissionController,
 * scoped to the lecturer's own sections throughout.
 */
class AssessmentMarkingController extends Controller
{
    use AuthorizesLecturerAccess;

    /** @var array<int, Collection<int, SectionStudent>> */
    private array $rosters = [];

    public function index(Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $assessments = Assessment::where('course_id', $course->id)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount($this->scoreCounts())])
            ->withCount($this->scoreCounts())
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $assessments->map(fn (Assessment $assessment) => array_merge($this->summary($assessment), [
                'children' => $assessment->children->map(fn (Assessment $child) => $this->summary($child))->values(),
            ]))->values(),
            'meta' => ['students' => $this->roster($course)->count()],
        ]);
    }

    public function show(Assessment $assessment): JsonResponse
    {
        $course = $this->authorizeAssessment($assessment);
        $assessment->load('rubric.criteria.levels');

        $roster = $this->roster($course);
        $userIds = $roster->pluck('user_id');

        $scores = $assessment->scores()->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $submissions = $assessment->submissions()->whereIn('user_id', $userIds)->withCount('files')->get()->keyBy('user_id');
        $groups = $this->groupsByUser($assessment, $userIds);
        $studentIds = $this->studentIdNumbers($userIds);

        $rows = $roster->map(function (SectionStudent $enrolment) use ($scores, $submissions, $groups, $studentIds) {
            $submission = $submissions->get($enrolment->user_id);

            return [
                'student' => [
                    'id' => $enrolment->user_id,
                    'name' => $enrolment->user?->name,
                    'student_id_number' => $studentIds[$enrolment->user_id] ?? null,
                ],
                'section' => $enrolment->section ? ['id' => $enrolment->section->id, 'name' => $enrolment->section->name] : null,
                'group' => $groups->get($enrolment->user_id),
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'is_late' => (bool) $submission->is_late,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'files_count' => (int) $submission->files_count,
                ] : null,
                'score' => $this->score($scores->get($enrolment->user_id)),
            ];
        })->values();

        $graded = $rows->filter(fn ($row) => $row['score'] && $row['score']['finalized_at']);

        return response()->json([
            'data' => array_merge($this->summary($assessment), [
                'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title],
                'description' => $assessment->description,
                'rubric' => $this->rubric($assessment),
                'stats' => [
                    'students' => $rows->count(),
                    'submitted' => $rows->whereNotNull('submission')->count(),
                    'graded' => $graded->count(),
                    'released' => $graded->filter(fn ($row) => $row['score']['is_released'])->count(),
                    'average_percentage' => $graded->isEmpty() ? null : round((float) $graded->avg(fn ($row) => $row['score']['percentage'] ?? 0), 2),
                ],
                'students' => $rows,
            ]),
        ]);
    }

    public function submission(Assessment $assessment, AssessmentSubmission $submission): JsonResponse
    {
        $this->authorizeSubmission($assessment, $submission);

        $submission->load(['user', 'files']);

        return response()->json([
            'data' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'notes' => $submission->notes,
                'is_late' => (bool) $submission->is_late,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'student' => ['id' => $submission->user?->id, 'name' => $submission->user?->name],
                'files' => $submission->files->map(fn (AssessmentSubmissionFile $file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'mime_type' => $file->file_type,
                    'size_bytes' => $file->file_size_bytes ? (int) $file->file_size_bytes : null,
                    'has_annotations' => $file->annotated_at !== null,
                    'is_stamped' => $file->graded_file_path !== null,
                ])->values(),
            ],
        ]);
    }

    /**
     * `?original=1` skips the grade-stamped copy, as the web allows lecturers to.
     */
    public function file(Request $request, Assessment $assessment, AssessmentSubmission $submission, AssessmentSubmissionFile $file): StreamedResponse
    {
        $this->authorizeSubmission($assessment, $submission);

        if ($file->assessment_submission_id !== $submission->id) {
            abort(404, 'File not found.');
        }

        $path = $request->boolean('original') ? $file->storage_path : $file->viewablePath();

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, 'This file is not stored on Lectura. Open it on the web.');
        }

        return Storage::disk('local')->download($path, $file->file_name);
    }

    /**
     * Enter or change one student's mark (and their group's, when they submitted as one).
     * Like the web's per-submission marking, a changed mark goes back to unreleased.
     */
    public function mark(Request $request, Assessment $assessment, User $user): JsonResponse
    {
        $course = $this->authorizeAssessment($assessment);

        if ($assessment->isParent()) {
            abort(422, 'This assessment is marked through its parts.');
        }

        if (! $this->roster($course)->contains('user_id', $user->id)) {
            abort(404, 'This student is not in your sections of this course.');
        }

        $assessment->load('rubric.criteria');
        $criteria = $assessment->rubric?->criteria ?? collect();
        $total = (float) $assessment->total_marks;

        $rules = ['feedback' => ['nullable', 'string', 'max:5000']];
        if ($criteria->isNotEmpty()) {
            $rules['criteria_marks'] = ['required', 'array'];
            foreach ($criteria as $criterion) {
                $rules["criteria_marks.{$criterion->id}"] = ['required', 'numeric', 'min:0', 'max:'.(float) $criterion->max_marks];
            }
        } else {
            $rules['raw_marks'] = ['required', 'numeric', 'min:0', 'max:'.$total];
        }
        $request->validate($rules);

        [$raw, $criteriaMarks] = $criteria->isNotEmpty()
            ? $this->rubricMarks($assessment, $criteria, (array) $request->input('criteria_marks'))
            : [round((float) $request->input('raw_marks'), 2), null];

        $percentage = $total > 0 ? round($raw / $total * 100, 2) : 0;
        // percentage × weightage, as the per-submission web path does (the manual grid's
        // raw × weightage disagrees whenever total_marks is not 100).
        $weighted = round($percentage * (float) $assessment->weightage / 100, 2);

        $submission = $assessment->submissions()->where('user_id', $user->id)->first();
        $targets = $submission?->student_group_id
            ? $assessment->submissions()->where('student_group_id', $submission->student_group_id)->get()
            : collect([$submission ?? $user]);

        foreach ($targets as $target) {
            $isSubmission = $target instanceof AssessmentSubmission;

            $values = [
                'tenant_id' => app('current_tenant')->id,
                'raw_marks' => $raw,
                'max_marks' => $total,
                'weighted_marks' => $weighted,
                'percentage' => $percentage,
                'is_computed' => false,
                'is_released' => false,
                'released_at' => null,
                'feedback' => $request->input('feedback'),
                'criteria_marks' => $criteriaMarks,
                'finalized_by' => $request->user()->id,
                'finalized_at' => now(),
            ];
            // A student marked without a submission keeps whatever link their score had.
            if ($isSubmission) {
                $values['assessment_submission_id'] = $target->id;
            }

            AssessmentScore::updateOrCreate(
                ['assessment_id' => $assessment->id, 'user_id' => $isSubmission ? $target->user_id : $target->id],
                $values,
            );

            if ($isSubmission && $target->status !== 'graded') {
                $target->update(['status' => 'graded']);
            }
        }

        $score = $assessment->scores()->where('user_id', $user->id)->first();

        return response()->json([
            'message' => $targets->count() > 1
                ? "Mark saved for the group ({$targets->count()} members). Release it when you are ready."
                : 'Mark saved. Release it when you are ready.',
            'data' => $this->score($score),
        ]);
    }

    /**
     * Releases finalized, unreleased scores in the lecturer's sections — all of them, or
     * `score_ids` (widened to the rest of each group, as on the web).
     */
    public function release(Request $request, Assessment $assessment): JsonResponse
    {
        $course = $this->authorizeAssessment($assessment);

        $request->validate(['score_ids' => ['nullable', 'array'], 'score_ids.*' => ['integer']]);

        $query = $assessment->scores()
            ->whereIn('user_id', $this->roster($course)->pluck('user_id'))
            ->whereNotNull('finalized_at')
            ->where('is_released', false);

        if ($request->filled('score_ids')) {
            $query->whereIn('id', $this->withGroupmates($assessment, collect($request->input('score_ids'))->map(fn ($id) => (int) $id)));
        }

        $scores = $query->with('user')->get();

        foreach ($scores as $score) {
            $score->update(['is_released' => true, 'released_at' => now()]);
            $score->user?->notify(new AssessmentMarksReleased($assessment, $score));
        }

        return response()->json([
            'message' => $scores->isEmpty()
                ? 'There were no marked, unreleased scores to release.'
                : "Marks released to {$scores->count()} ".($scores->count() === 1 ? 'student.' : 'students.'),
            'data' => ['released' => $scores->count()],
        ]);
    }

    /**
     * Unlike the web, this checks the score belongs to the assessment and retracts the whole
     * group, mirroring how release widens to it.
     */
    public function unrelease(Assessment $assessment, AssessmentScore $score): JsonResponse
    {
        $course = $this->authorizeAssessment($assessment);

        if ($score->assessment_id !== $assessment->id || ! $this->roster($course)->contains('user_id', $score->user_id)) {
            abort(404, 'Score not found.');
        }

        $count = $assessment->scores()
            ->whereIn('id', $this->withGroupmates($assessment, collect([$score->id])))
            ->where('is_released', true)
            ->update(['is_released' => false, 'released_at' => null]);

        return response()->json([
            'message' => 'Marks retracted.',
            'data' => ['retracted' => $count, 'score' => $this->score($score->refresh())],
        ]);
    }

    private function authorizeAssessment(Assessment $assessment): Course
    {
        $this->ensureLecturer();

        $course = $assessment->course;
        if (! $course) {
            abort(404, 'Assessment not found.');
        }

        $this->authorizeCourse($course);

        return $course;
    }

    private function authorizeSubmission(Assessment $assessment, AssessmentSubmission $submission): void
    {
        $course = $this->authorizeAssessment($assessment);

        if ($submission->assessment_id !== $assessment->id || ! $this->roster($course)->contains('user_id', $submission->user_id)) {
            abort(404, 'Submission not found.');
        }
    }

    /**
     * Active enrolments in the sections this lecturer may see — the web's score list.
     *
     * @return Collection<int, SectionStudent>
     */
    private function roster(Course $course): Collection
    {
        return $this->rosters[$course->id] ??= SectionStudent::whereIn('section_id', $this->lecturerSectionIds($course))
            ->where('is_active', true)
            ->with(['user', 'section'])
            ->get()
            ->unique('user_id')
            ->sortBy(fn (SectionStudent $enrolment) => mb_strtolower((string) $enrolment->user?->name))
            ->values();
    }

    /**
     * @return array{0: float, 1: array<string, float>}
     */
    private function rubricMarks(Assessment $assessment, Collection $criteria, array $input): array
    {
        // Same formula as the web: weighted only when every criterion has a positive weight.
        $weighted = $criteria->every(fn (RubricCriteria $c) => $c->weightage !== null && (float) $c->weightage > 0);
        $weightSum = $weighted ? (float) $criteria->sum(fn (RubricCriteria $c) => (float) $c->weightage) : 0.0;
        $total = (float) $assessment->total_marks;

        $raw = 0.0;
        $marks = [];
        foreach ($criteria as $criterion) {
            $score = (float) ($input[$criterion->id] ?? 0);
            $marks[(string) $criterion->id] = $score;

            if ($weighted && $weightSum > 0) {
                $max = (float) $criterion->max_marks;
                if ($max > 0) {
                    $raw += ($score / $max) * ((float) $criterion->weightage / $weightSum) * $total;
                }
            } else {
                $raw += $score;
            }
        }

        return [round(min($raw, $total), 2), $marks];
    }

    /**
     * @param  Collection<int, int>  $scoreIds
     * @return array<int, int>
     */
    private function withGroupmates(Assessment $assessment, Collection $scoreIds): array
    {
        $groupIds = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->whereIn('id', AssessmentScore::whereIn('id', $scoreIds)->where('assessment_id', $assessment->id)->select('assessment_submission_id'))
            ->whereNotNull('student_group_id')
            ->pluck('student_group_id');

        if ($groupIds->isEmpty()) {
            return $scoreIds->all();
        }

        return $scoreIds->merge(
            AssessmentScore::where('assessment_id', $assessment->id)
                ->whereIn('assessment_submission_id', AssessmentSubmission::where('assessment_id', $assessment->id)->whereIn('student_group_id', $groupIds)->select('id'))
                ->pluck('id')
        )->unique()->values()->all();
    }

    /**
     * @return Collection<int, array{id: int, name: string, is_leader: bool}>
     */
    private function groupsByUser(Assessment $assessment, Collection $userIds): Collection
    {
        if (! $assessment->student_group_set_id) {
            return collect();
        }

        return StudentGroupMember::whereIn('user_id', $userIds)
            ->whereHas('group', fn ($q) => $q->where('student_group_set_id', $assessment->student_group_set_id))
            ->with('group')
            ->get()
            ->mapWithKeys(fn (StudentGroupMember $member) => [$member->user_id => [
                'id' => $member->group->id,
                'name' => $member->group->name,
                'is_leader' => $member->role === 'leader',
            ]]);
    }

    private function scoreCounts(): array
    {
        return [
            'scores as graded_count' => fn ($q) => $q->whereNotNull('finalized_at'),
            'scores as released_count' => fn ($q) => $q->where('is_released', true),
            'submissions as submissions_count',
        ];
    }

    private function summary(Assessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'title' => $assessment->title,
            'type' => $assessment->type,
            'status' => $assessment->status,
            'total_marks' => (float) $assessment->total_marks,
            'weightage' => (float) $assessment->weightage,
            'due_date' => $assessment->due_date?->toIso8601String(),
            'requires_submission' => (bool) $assessment->requires_submission,
            'is_group' => $assessment->usesGroupSubmission(),
            'parent_id' => $assessment->parent_id,
            'counts' => [
                'submissions' => (int) ($assessment->submissions_count ?? 0),
                'graded' => (int) ($assessment->graded_count ?? 0),
                'released' => (int) ($assessment->released_count ?? 0),
            ],
        ];
    }

    private function rubric(Assessment $assessment): ?array
    {
        $criteria = $assessment->rubric?->criteria;

        if (! $criteria || $criteria->isEmpty()) {
            return null;
        }

        return [
            'is_weighted' => $criteria->every(fn (RubricCriteria $c) => $c->weightage !== null && (float) $c->weightage > 0),
            'criteria' => $criteria->map(fn (RubricCriteria $criterion) => [
                'id' => $criterion->id,
                'title' => $criterion->title,
                'description' => $criterion->description,
                'max_marks' => (float) $criterion->max_marks,
                'weightage' => $criterion->weightage !== null ? (float) $criterion->weightage : null,
                'levels' => $criterion->levels->map(fn ($level) => [
                    'label' => $level->label,
                    'description' => $level->description,
                    'marks' => (float) $level->marks,
                ])->values(),
            ])->values(),
        ];
    }

    private function score(?AssessmentScore $score): ?array
    {
        if (! $score) {
            return null;
        }

        return [
            'id' => $score->id,
            'raw_marks' => $score->raw_marks !== null ? (float) $score->raw_marks : null,
            'max_marks' => $score->max_marks !== null ? (float) $score->max_marks : null,
            'percentage' => $score->percentage !== null ? (float) $score->percentage : null,
            'weighted_marks' => $score->weighted_marks !== null ? (float) $score->weighted_marks : null,
            'criteria_marks' => $score->criteria_marks ? collect($score->criteria_marks)->map(fn ($v) => (float) $v) : null,
            'feedback' => $score->feedback,
            'is_computed' => (bool) $score->is_computed,
            'is_released' => (bool) $score->is_released,
            'released_at' => $score->released_at?->toIso8601String(),
            'finalized_at' => $score->finalized_at?->toIso8601String(),
        ];
    }
}
