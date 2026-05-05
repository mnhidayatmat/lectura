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
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $scores = $assessment->scores()->get()->keyBy('user_id');
        $submissions = $assessment->requires_submission
            ? $assessment->submissions()->get()->keyBy('user_id')
            : collect();

        // Summary stats — count any score record as "graded" (computed or manual)
        $stats = [
            'total' => $students->count(),
            'submitted' => $submissions->count(),
            'graded' => $scores->count(),
            'released' => $scores->where('is_released', true)->count(),
            'avg' => $scores->isNotEmpty() ? round($scores->avg('percentage'), 1) : null,
            'passing' => $scores->where('percentage', '>=', 50)->count(),
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
        $existingAnswerScripts = $scores->map(fn ($s) => $s->answer_script_path
            ? ['score_id' => $s->id, 'name' => $s->answer_script_filename]
            : null
        )->filter();

        $criteria = $assessment->rubric?->criteria ?? collect();
        $hasRubric = $criteria->isNotEmpty();

        return view('tenant.assessments.scores.manual', compact(
            'tenant', 'course', 'assessment', 'students',
            'existingScores', 'existingCriteriaMarks', 'existingAnswerScripts',
            'criteria', 'hasRubric'
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
        $rules = [
            'answer_script_files' => ['nullable', 'array'],
            'answer_script_files.*' => ['nullable', 'file', 'max:25600', 'mimes:pdf,jpg,jpeg,png'],
            'remove_answer_scripts' => ['nullable', 'array'],
            'remove_answer_scripts.*' => ['nullable', 'in:0,1,true,false'],
        ];
        if ($hasRubric) {
            $rules['criteria_marks'] = ['nullable', 'array'];
            foreach ($criteria as $criterion) {
                $rules['criteria_marks.*.'.$criterion->id] = [
                    'nullable', 'numeric', 'min:0', 'max:'.(float) $criterion->max_marks,
                ];
            }
        } else {
            $rules['marks'] = ['nullable', 'array'];
            $rules['marks.*'] = ['nullable', 'numeric', 'min:0', 'max:'.(float) $assessment->total_marks];
        }
        $request->validate($rules);

        $tenant = app('current_tenant');
        $count = 0;
        $scriptFiles = $request->file('answer_script_files', []) ?? [];
        $removeScripts = collect($request->input('remove_answer_scripts', []))
            ->filter(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))
            ->keys()
            ->all();
        $existingScores = $assessment->scores()->get()->keyBy('user_id');

        $applyScriptFile = function (AssessmentScore $score, $userId) use (&$scriptFiles, $removeScripts) {
            if (isset($scriptFiles[$userId]) && $scriptFiles[$userId]) {
                if ($score->answer_script_path) {
                    Storage::disk('local')->delete($score->answer_script_path);
                }
                $file = $scriptFiles[$userId];
                $score->answer_script_path = $file->store('assessment-answer-scripts', 'local');
                $score->answer_script_filename = $file->getClientOriginalName();
                $score->save();
            } elseif (in_array((string) $userId, $removeScripts, true)) {
                if ($score->answer_script_path) {
                    Storage::disk('local')->delete($score->answer_script_path);
                }
                $score->answer_script_path = null;
                $score->answer_script_filename = null;
                $score->save();
            }
        };

        // Same weighting strategy used by AssessmentSubmissionController::storeMark:
        // weighted only when EVERY criterion has an explicit positive weight,
        // otherwise plain sum so partial-weight rubrics don't undercount.
        $isWeighted = $hasRubric && $criteria->every(
            fn ($c) => $c->weightage !== null && (float) $c->weightage > 0
        );

        $processedUserIds = [];

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

                $score = AssessmentScore::updateOrCreate(
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
                $applyScriptFile($score, $userId);
                $processedUserIds[] = (int) $userId;
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

                $score = AssessmentScore::updateOrCreate(
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
                $applyScriptFile($score, $userId);
                $processedUserIds[] = (int) $userId;
                $count++;
            }
        }

        // Handle file-only changes (upload or remove without entering marks)
        // — only when an existing score record exists; we don't fabricate a 0-mark
        // score just to attach a script.
        $fileOnlyUserIds = collect(array_keys($scriptFiles))
            ->merge($removeScripts)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->diff($processedUserIds);
        foreach ($fileOnlyUserIds as $userId) {
            if (! $existingScores->has($userId)) {
                continue;
            }
            $applyScriptFile($existingScores->get($userId), $userId);
        }

        return redirect()->route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment])
            ->with('success', "Marks saved for {$count} students.");
    }

    public function viewAnswerScript(string $tenantSlug, Course $course, Assessment $assessment, AssessmentScore $score): BinaryFileResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id || $score->assessment_id !== $assessment->id || ! $score->answer_script_path) {
            abort(404);
        }

        $absolutePath = Storage::disk('local')->path($score->answer_script_path);
        if (! file_exists($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath);
    }

    public function downloadAnswerScript(string $tenantSlug, Course $course, Assessment $assessment, AssessmentScore $score): StreamedResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id || $score->assessment_id !== $assessment->id || ! $score->answer_script_path) {
            abort(404);
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
