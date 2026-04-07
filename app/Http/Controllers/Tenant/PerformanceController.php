<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Jobs\GeneratePerformanceSuggestions;
use App\Models\Course;
use App\Models\PerformanceAiSuggestion;
use App\Models\SectionStudent;
use App\Models\User;
use App\Services\ActiveLearning\TierGateService;
use App\Services\Performance\PerformanceAggregatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        protected PerformanceAggregatorService $aggregator,
    ) {}

    // ── Lecturer Views ───────────────────────────────────────────────

    public function lecturerIndex(): View
    {
        $courses = Course::whereIn('id', $this->accessibleCourseIds())
            ->with('sections.activeStudents')
            ->get();

        return view('tenant.performance.lecturer-index', compact('courses'));
    }

    public function lecturerCourse(Request $request, string $tenantSlug, Course $course): View
    {
        $this->authorizeLecturer($course);

        $sectionId = $request->query('section');
        $section = $sectionId ? $this->lecturerSections($course)->find($sectionId) : null;

        $data = $this->aggregator->getCoursePerformance($course, $section);

        $latestSuggestion = PerformanceAiSuggestion::where('course_id', $course->id)
            ->where('suggestion_type', 'course_overview')
            ->whereNull('user_id')
            ->latest()
            ->first();

        return view('tenant.performance.lecturer-course', [
            'course' => $course,
            'data' => $data,
            'selectedSection' => $section,
            'latestSuggestion' => $latestSuggestion,
        ]);
    }

    public function lecturerStudent(string $tenantSlug, Course $course, User $student): View
    {
        $this->authorizeLecturer($course);

        $data = $this->aggregator->getStudentCoursePerformance($student, $course);

        $latestSuggestion = PerformanceAiSuggestion::where('course_id', $course->id)
            ->where('user_id', $student->id)
            ->where('suggestion_type', 'student_individual')
            ->latest()
            ->first();

        return view('tenant.performance.lecturer-student', [
            'course' => $course,
            'student' => $student,
            'data' => $data,
            'latestSuggestion' => $latestSuggestion,
        ]);
    }

    // ── Student Views ────────────────────────────────────────────────

    public function studentIndex(): View
    {
        $data = $this->aggregator->getStudentOverview(auth()->user());

        return view('tenant.performance.student-index', compact('data'));
    }

    public function studentCourse(string $tenantSlug, Course $course): View
    {
        $this->authorizeStudent($course);

        $data = $this->aggregator->getStudentCoursePerformance(auth()->user(), $course);

        $latestSuggestion = PerformanceAiSuggestion::where('course_id', $course->id)
            ->where('user_id', auth()->id())
            ->where('suggestion_type', 'student_individual')
            ->latest()
            ->first();

        return view('tenant.performance.student-course', [
            'course' => $course,
            'data' => $data,
            'latestSuggestion' => $latestSuggestion,
        ]);
    }

    // ── AI Suggestions ───────────────────────────────────────────────

    public function generateAiSuggestions(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeLecturer($course);
        TierGateService::assertProFeature(auth()->user(), 'ai_performance_analysis');

        $studentId = $request->input('student_id');
        $student = $studentId ? User::findOrFail($studentId) : null;

        $suggestion = PerformanceAiSuggestion::create([
            'tenant_id' => app('current_tenant')->id,
            'course_id' => $course->id,
            'user_id' => $student?->id,
            'generated_by' => auth()->id(),
            'suggestion_type' => $student ? 'student_individual' : 'course_overview',
            'status' => 'processing',
        ]);

        GeneratePerformanceSuggestions::dispatch($suggestion, $course, $student);

        return back()->with('success', __('performance.ai_generating'));
    }

    public function aiSuggestionStatus(string $tenantSlug, Course $course): JsonResponse
    {
        $this->authorizeLecturer($course);

        $latest = PerformanceAiSuggestion::where('course_id', $course->id)
            ->latest()
            ->first();

        return response()->json([
            'status' => $latest?->status ?? 'none',
            'suggestion_type' => $latest?->suggestion_type,
        ]);
    }

    // ── Authorization ────────────────────────────────────────────────

    protected function authorizeLecturer(Course $course): void
    {
        $this->authorizeCourseAccess($course);
    }

    protected function authorizeStudent(Course $course): void
    {
        $enrolled = SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->exists();

        if (! $enrolled) {
            abort(403);
        }
    }
}
