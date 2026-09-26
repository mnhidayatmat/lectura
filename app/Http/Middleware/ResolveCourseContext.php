<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Course\CourseContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the lecturer's course context for the tenant web group: binds
 * `current_course` and shares it with views. Students are untouched — their
 * flows stay course-explicit.
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

        $course = $this->courseContext->get($user, $tenant)
            ?? $this->courseContext->autoSelectIfSingle($user, $tenant);

        if ($course) {
            app()->instance('current_course', $course);
            View::share('currentCourse', $course);
            View::share('accessibleCoursesForSwitcher', $this->courseContext->accessibleCourses($user, $tenant));
        } else {
            app()->forgetInstance('current_course');
            View::share('currentCourse', null);
        }

        return $next($request);
    }
}
