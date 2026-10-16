<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\Course;
use App\Models\SectionStudent;
use App\Models\User;
use App\Services\Course\CourseContextService;
use App\Services\Episodes\WatchCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The tenant home. Lecturers land on the overview of their selected course;
 * with several courses and none selected they are sent to the course picker.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected CourseContextService $courseContext,
    ) {}

    public function __invoke(string $tenantSlug): View|RedirectResponse
    {
        $tenant = app('current_tenant');
        $role = auth()->user()->roleInTenant($tenant->id);

        if ($role !== 'student') {
            if (app()->bound('current_course')) {
                return redirect()->route('tenant.courses.show', [$tenant->slug, app('current_course')]);
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
            'dueAssessments' => $role === 'student' ? $this->dueAssessments(auth()->user()) : collect(),
            // The Watch banner must never break the dashboard.
            'watchFeatured' => $role === 'student' ? rescue(fn () => app(WatchCatalog::class)->home(auth()->user())['featured'], null, report: true) : null,
        ]);
    }

    /**
     * The next five assessments the student must hand in, for the highlight at the
     * top of the dashboard: submitted ones turn green, and the rest go from blue
     * to amber within a week of the due date and red within 48 hours of it.
     */
    private function dueAssessments(User $user): Collection
    {
        $courseIds = SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('section:id,course_id')
            ->get()
            ->pluck('section.course_id')
            ->filter()
            ->unique();

        $assessments = Assessment::whereIn('course_id', $courseIds)
            ->where('requires_submission', true)
            ->whereIn('status', ['active', 'completed'])
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->with('course')
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        $groupIds = $assessments
            ->map(fn (Assessment $assessment) => $assessment->groupForUser($user->id)?->id)
            ->filter()
            ->values();

        $submittedIds = AssessmentSubmission::whereIn('assessment_id', $assessments->pluck('id'))
            ->where(function ($q) use ($user, $groupIds) {
                $q->where('user_id', $user->id);

                if ($groupIds->isNotEmpty()) {
                    $q->orWhereIn('student_group_id', $groupIds);
                }
            })
            ->pluck('assessment_id');

        return $assessments->map(function (Assessment $assessment) use ($submittedIds) {
            $submitted = $submittedIds->contains($assessment->id);
            $hoursLeft = now()->diffInHours($assessment->due_date);

            return [
                'assessment' => $assessment,
                'submitted' => $submitted,
                'tone' => match (true) {
                    $submitted => 'submitted',
                    $hoursLeft < 48 => 'urgent',
                    $hoursLeft < 24 * 7 => 'soon',
                    default => 'later',
                },
            ];
        });
    }
}
