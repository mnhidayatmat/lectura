<?php

declare(strict_types=1);

namespace App\Services\Course;

use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Netflix-style course context: remembers the course a lecturer is currently
 * "teaching in" for the current tenant, so feature pages can be course-scoped
 * without a two-step pick-a-course flow every time.
 */
class CourseContextService
{
    protected function sessionKey(Tenant $tenant): string
    {
        return 'course_context_'.$tenant->id;
    }

    /**
     * The stored course context for the current tenant, or null when none is
     * set, the course disappeared, or the user may no longer access it.
     */
    public function get(User $user, Tenant $tenant): ?Course
    {
        $courseId = Session::get($this->sessionKey($tenant));

        if (! $courseId) {
            return null;
        }

        $course = Course::find($courseId);

        if (! $course || $course->tenant_id !== $tenant->id || ! $this->canAccess($user, $tenant, $course)) {
            Session::forget($this->sessionKey($tenant));

            return null;
        }

        return $course;
    }

    /**
     * Store the course as the current context. Cross-tenant courses 404,
     * courses the user may not access 403 — same rules as course pages.
     */
    public function set(User $user, Tenant $tenant, Course $course): void
    {
        if ($course->tenant_id !== $tenant->id) {
            throw new NotFoundHttpException;
        }

        if (! $this->canAccess($user, $tenant, $course)) {
            throw new AccessDeniedHttpException;
        }

        Session::put($this->sessionKey($tenant), $course->id);
    }

    public function clear(Tenant $tenant): void
    {
        Session::forget($this->sessionKey($tenant));
    }

    /**
     * All courses the user can access in this tenant (owned + section-assigned;
     * tenant admins see everything), for the switcher list and auto-select.
     */
    public function accessibleCourses(User $user, Tenant $tenant): Collection
    {
        $query = Course::query()
            ->with(['academicTerm'])
            ->withCount(['sections'])
            ->orderBy('code');

        if (! $user->hasRoleInTenant($tenant->id, ['admin'])) {
            $ownedIds = Course::where('lecturer_id', $user->id)->pluck('id');
            $sectionCourseIds = Section::whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('course_id');

            $query->whereIn('id', $ownedIds->merge($sectionCourseIds)->unique());
        }

        return $query->get();
    }

    /**
     * Lecturers with exactly one accessible course get it auto-selected so they
     * never see the picker.
     */
    public function autoSelectIfSingle(User $user, Tenant $tenant): ?Course
    {
        $existing = $this->get($user, $tenant);

        if ($existing) {
            return $existing;
        }

        $courses = $this->accessibleCourses($user, $tenant);

        if ($courses->count() === 1) {
            $this->set($user, $tenant, $courses->first());

            return $courses->first();
        }

        return null;
    }

    /**
     * Same access rules as AuthorizesCourseAccess::authorizeCourseAccess.
     */
    protected function canAccess(User $user, Tenant $tenant, Course $course): bool
    {
        if ($user->hasRoleInTenant($tenant->id, ['admin'])) {
            return true;
        }

        if ($user->id === $course->lecturer_id) {
            return true;
        }

        return Section::where('course_id', $course->id)
            ->whereHas('lecturers', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
