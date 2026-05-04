<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Assessment;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Models\User;
use App\Services\Assessment\AssessmentScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentScoreController extends Controller
{
    use AuthorizesCourseAccess;
    public function __construct(
        protected AssessmentScoreService $scoreService,
    ) {}

    public function index(string $tenantSlug, Course $course, Assessment $assessment): View
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $tenant = app('current_tenant');

        // All enrolled students across sections taught by this lecturer
        $studentIds = SectionStudent::whereIn('section_id', $this->lecturerSectionIds($course))
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');

        $students = User::whereIn('id', $studentIds)->orderBy('name')->get();

        // Scores & submissions keyed by user_id
        $scores      = $assessment->scores()->get()->keyBy('user_id');
        $submissions = $assessment->requires_submission
            ? $assessment->submissions()->get()->keyBy('user_id')
            : collect();

        // Summary stats — count any score record as "graded" (computed or manual)
        $stats = [
            'total'     => $students->count(),
            'submitted' => $submissions->count(),
            'graded'    => $scores->count(),
            'released'  => $scores->where('is_released', true)->count(),
            'avg'       => $scores->isNotEmpty() ? round($scores->avg('percentage'), 1) : null,
            'passing'   => $scores->where('percentage', '>=', 50)->count(),
        ];

        return view('tenant.assessments.scores.index', compact(
            'tenant', 'course', 'assessment', 'students', 'scores', 'submissions', 'stats'
        ));
    }

    public function compute(string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $count = $this->scoreService->computeScores($assessment);

        return back()->with('success', "Scores computed for {$count} students.");
    }

    public function manualEntry(string $tenantSlug, Course $course, Assessment $assessment): View
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $tenant = app('current_tenant');
        $assessment->load('rubric.criteria');

        // Get all enrolled students
        $studentIds = SectionStudent::whereIn('section_id', $this->lecturerSectionIds($course))
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');
        $students = User::whereIn('id', $studentIds)->orderBy('name')->get();

        // Existing scores — both totals and per-criterion JSON, keyed by user_id
        $scores = $assessment->scores()->get()->keyBy('user_id');
        $existingScores = $scores->map(fn ($s) => $s->raw_marks);
        $existingCriteriaMarks = $scores->map(fn ($s) => is_array($s->criteria_marks) ? $s->criteria_marks : []);

        $criteria = $assessment->rubric?->criteria ?? collect();
        $hasRubric = $criteria->isNotEmpty();

        return view('tenant.assessments.scores.manual', compact(
            'tenant', 'course', 'assessment', 'students',
            'existingScores', 'existingCriteriaMarks', 'criteria', 'hasRubric'
        ));
    }

    public function storeManual(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $assessment->load('rubric.criteria');
        $criteria = $assessment->rubric?->criteria ?? collect();
        $hasRubric = $criteria->isNotEmpty();

        // Build dynamic validation rules. Each criterion gets its own min/max bound.
        $rules = [];
        if ($hasRubric) {
            $rules['criteria_marks'] = ['nullable', 'array'];
            foreach ($criteria as $criterion) {
                $rules['criteria_marks.*.'.$criterion->id] = [
                    'nullable', 'numeric', 'min:0', 'max:'.(float) $criterion->max_marks,
                ];
            }
        } else {
            $rules['marks'] = ['required', 'array'];
            $rules['marks.*'] = ['nullable', 'numeric', 'min:0', 'max:'.(float) $assessment->total_marks];
        }
        $request->validate($rules);

        $tenant = app('current_tenant');
        $count = 0;

        // Same weighting strategy used by AssessmentSubmissionController::storeMark.
        $isWeighted = $hasRubric && $criteria->contains(
            fn ($c) => $c->weightage !== null && (float) $c->weightage > 0
        );

        if ($hasRubric) {
            $payload = $request->input('criteria_marks', []);
            foreach ($payload as $userId => $perCriterion) {
                if (! is_array($perCriterion)) {
                    continue;
                }

                // Skip rows where every cell is empty.
                $hasAnyValue = collect($perCriterion)->contains(
                    fn ($v) => $v !== null && $v !== ''
                );
                if (! $hasAnyValue) {
                    continue;
                }

                $rawMarks = 0.0;
                $criteriaMarksInput = [];
                foreach ($criteria as $criterion) {
                    $score = (float) ($perCriterion[$criterion->id] ?? 0);
                    $criteriaMarksInput[(string) $criterion->id] = $score;
                    if ($isWeighted) {
                        $max = (float) $criterion->max_marks;
                        $weight = (float) ($criterion->weightage ?? 0);
                        if ($max > 0 && $weight > 0) {
                            $rawMarks += ($score / $max) * ($weight / 100) * (float) $assessment->total_marks;
                        }
                    } else {
                        $rawMarks += $score;
                    }
                }
                $rawMarks = round(min($rawMarks, (float) $assessment->total_marks), 2);

                $maxMarks = (float) $assessment->total_marks;
                $percentage = $maxMarks > 0 ? round(($rawMarks / $maxMarks) * 100, 2) : 0;
                $weightedMarks = round($percentage * (float) $assessment->weightage / 100, 2);

                AssessmentScore::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'user_id' => $userId],
                    [
                        'tenant_id' => $tenant->id,
                        'raw_marks' => $rawMarks,
                        'max_marks' => $maxMarks,
                        'weighted_marks' => $weightedMarks,
                        'percentage' => $percentage,
                        'is_computed' => false,
                        'criteria_marks' => $criteriaMarksInput,
                        'finalized_by' => auth()->id(),
                        'finalized_at' => now(),
                    ]
                );
                $count++;
            }
        } else {
            foreach ($request->input('marks', []) as $userId => $rawMarks) {
                if ($rawMarks === null || $rawMarks === '') {
                    continue;
                }

                $rawMarks = (float) $rawMarks;
                $percentage = $assessment->total_marks > 0
                    ? ($rawMarks / $assessment->total_marks) * 100
                    : 0;
                $weightedMarks = $rawMarks * ($assessment->weightage / 100);

                AssessmentScore::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'user_id' => $userId],
                    [
                        'tenant_id' => $tenant->id,
                        'raw_marks' => round($rawMarks, 2),
                        'max_marks' => $assessment->total_marks,
                        'weighted_marks' => round($weightedMarks, 2),
                        'percentage' => round($percentage, 2),
                        'is_computed' => false,
                        'finalized_by' => auth()->id(),
                        'finalized_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return redirect()->route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment])
            ->with('success', "Marks saved for {$count} students.");
    }
}
