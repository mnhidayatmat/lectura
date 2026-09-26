<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Services\Course\CourseContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The tenant home. Lecturers land on the home of their selected course; with
 * several courses and none selected they are sent to the course picker first.
 */
class DashboardController extends Controller
{
    use AuthorizesCourseAccess;

    public function __construct(
        protected CourseContextService $courseContext,
    ) {}

    public function __invoke(string $tenantSlug): View|RedirectResponse
    {
        $tenant = app('current_tenant');
        $role = auth()->user()->roleInTenant($tenant->id);

        if ($role !== 'student') {
            if (app()->bound('current_course')) {
                return $this->courseHome(app('current_course'));
            }

            $hasCourses = $this->courseContext->accessibleCourses(auth()->user(), $tenant)
                ->contains(fn (Course $course) => $course->status !== 'archived');

            if ($hasCourses) {
                return redirect()->route('tenant.course-context.picker', $tenant->slug);
            }
        }

        return view('tenant.dashboard', [
            'tenant' => $tenant,
            'role' => $role,
            'courseCount' => 0,
            'studentCount' => 0,
            'avgAttendance' => null,
            'courses' => collect(),
            'todaySchedule' => collect(),
        ]);
    }

    protected function courseHome(Course $course): View
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        $course->load([
            'learningOutcomes.programmeLearningOutcomes',
            'topics',
            'sections.academicTerm',
            'academicTerm',
            'assessments' => fn ($q) => $q->topLevel()->orderBy('sort_order'),
        ])->loadCount(['activeLearningPlans', 'studentGroupSets']);

        $term = $this->courseContext->termFor($course);
        $numWeeks = max((int) $course->num_weeks, 1);
        $currentWeek = $term?->isCurrent() && $term->start_date
            ? min((int) floor($term->start_date->diffInDays(now()) / 7) + 1, $numWeeks)
            : null;
        $weekTopics = $currentWeek ? $course->topics->where('week_number', $currentWeek)->values() : collect();

        $mySections = $this->lecturerSections($course)->where('is_active', true)->get();
        $sectionIds = $mySections->pluck('id');

        $today = strtolower(now()->format('l'));
        $todaySlots = $mySections
            ->flatMap(fn (Section $section) => collect($section->schedule ?? [])
                ->filter(fn ($slot) => ($slot['day'] ?? '') === $today)
                ->map(fn ($slot) => (object) [
                    'section_id' => $section->id,
                    'section_name' => $section->name,
                    'start_time' => $slot['start_time'] ?? '',
                    'end_time' => $slot['end_time'] ?? '',
                    'location' => $slot['location'] ?? null,
                    'type' => $slot['type'] ?? 'lecture',
                ]))
            ->sortBy('start_time')
            ->values();

        $liveSession = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'active')
            ->with('section:id,name')
            ->latest('started_at')
            ->first();

        $endedSessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->where('status', 'ended')
            ->withCount([
                'records as attended_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
                'records as total_count',
            ])
            ->get();
        $rates = $endedSessions->filter(fn ($s) => $s->total_count > 0)->map(fn ($s) => $s->attended_count / $s->total_count);
        $avgAttendance = $rates->isNotEmpty() ? (int) round($rates->avg() * 100) : null;

        $upcomingAssessments = Assessment::where('course_id', $course->id)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now()->startOfDay())
            ->orderBy('due_date')
            ->take(5)
            ->get();

        $assessmentWeight = (float) $course->assessments->sum('weightage');
        $cloCount = $course->learningOutcomes->count();
        $mappedClos = $course->learningOutcomes->filter(fn ($clo) => $clo->programmeLearningOutcomes->isNotEmpty())->count();

        $slug = $tenant->slug;
        $readiness = collect([
            ['label' => __('nav.ready_clos'), 'done' => $cloCount > 0, 'url' => route('tenant.courses.show', [$slug, $course])],
            ['label' => __('nav.ready_topics'), 'done' => $course->topics->count() >= $numWeeks, 'url' => route('tenant.courses.show', [$slug, $course])],
            ['label' => __('nav.ready_mapping'), 'done' => $cloCount > 0 && $mappedClos === $cloCount, 'url' => route('tenant.clo-plo.edit', [$slug, $course])],
            ['label' => __('nav.ready_sections'), 'done' => $term?->isCurrent() && $course->sections->contains(fn (Section $s) => $s->term()?->id === $term->id), 'url' => route('tenant.courses.show', [$slug, $course])],
            ['label' => __('nav.ready_assessments'), 'done' => abs($assessmentWeight - 100) < 0.01, 'url' => route('tenant.assessments.index', [$slug, $course])],
        ]);

        $otherCourses = $this->courseContext->accessibleCourses($user, $tenant)
            ->reject(fn (Course $c) => $c->id === $course->id || $c->status === 'archived')
            ->values();

        return view('tenant.course-home', [
            'tenant' => $tenant,
            'course' => $course,
            'term' => $term,
            'numWeeks' => $numWeeks,
            'currentWeek' => $currentWeek,
            'weekTopics' => $weekTopics,
            'todaySlots' => $todaySlots,
            'liveSession' => $liveSession,
            'studentCount' => $course->totalStudents(),
            'sectionCount' => $mySections->count(),
            'sessionsHeld' => $endedSessions->count(),
            'avgAttendance' => $avgAttendance,
            'assessmentWeight' => $assessmentWeight,
            'upcomingAssessments' => $upcomingAssessments,
            'readiness' => $readiness,
            'otherCourses' => $otherCourses,
            'termFor' => fn (Course $c) => $this->courseContext->termFor($c),
        ]);
    }
}
