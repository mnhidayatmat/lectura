<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Course;
use App\Services\Course\CourseContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the lecturer's course context for the tenant web group: binds
 * `current_course` and shares it with views. Opening any page of a course
 * makes that course the context, so the chrome never shows a different course
 * from the page. Students are untouched — their flows stay course-explicit.
 */
class ResolveCourseContext
{
    public function __construct(
        protected CourseContextService $courseContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app('current_tenant');
        $user = $request->user();

        if (! $user || $user->roleInTenant($tenant->id) === 'student') {
            return $next($request);
        }

        $this->followRouteCourse($request);

        $course = $this->courseContext->get($user, $tenant)
            ?? $this->courseContext->autoSelectIfSingle($user, $tenant);

        View::share('accessibleCoursesForSwitcher', $this->courseContext->accessibleCourses($user, $tenant));

        if ($course) {
            app()->instance('current_course', $course);
            View::share('currentCourse', $course);
        } else {
            app()->forgetInstance('current_course');
            View::share('currentCourse', null);
        }

        return $next($request);
    }

    /**
     * A course page (any route with a {course} parameter) switches the context
     * to its course when the user may open it; access denial is left to the
     * controller.
     */
    protected function followRouteCourse(Request $request): void
    {
        $param = $request->route('course');

        if ($param === null) {
            return;
        }

        $course = $param instanceof Course ? $param : Course::find((int) $param);
        $tenant = app('current_tenant');

        if ($course && (int) $course->tenant_id === (int) $tenant->id && $this->courseContext->canAccess($request->user(), $tenant, $course)) {
            $this->courseContext->set($request->user(), $tenant, $course);
        }
    }
}
