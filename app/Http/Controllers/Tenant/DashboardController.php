<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Course\CourseContextService;
use Illuminate\Http\RedirectResponse;
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
        ]);
    }
}
