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

        // Get all enrolled students
        $studentIds = SectionStudent::whereIn('section_id', $this->lecturerSectionIds($course))
            ->where('is_active', true)
            ->distinct()
            ->pluck('user_id');
        $students = User::whereIn('id', $studentIds)->orderBy('name')->get();

        // Existing scores
        $existingScores = $assessment->scores()->pluck('raw_marks', 'user_id');

        return view('tenant.assessments.scores.manual', compact('tenant', 'course', 'assessment', 'students', 'existingScores'));
    }

    public function storeManual(Request $request, string $tenantSlug, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCourseAccess($course);
        if ($assessment->course_id !== $course->id) {
            abort(403);
        }

        $request->validate([
            'marks' => ['required', 'array'],
            'marks.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tenant = app('current_tenant');
        $count = 0;

        foreach ($request->marks as $userId => $rawMarks) {
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

        return redirect()->route('tenant.assessments.scores.index', [$tenant->slug, $course, $assessment])
            ->with('success', "Marks saved for {$count} students.");
    }
}
