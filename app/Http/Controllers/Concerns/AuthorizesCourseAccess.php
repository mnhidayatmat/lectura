<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\Section;

trait AuthorizesCourseAccess
{
    /**
     * Abort 403 unless the authenticated user can access this course.
     * Access is granted to: primary course lecturer, tenant admins,
     * and lecturers who are assigned to at least one section of the course.
     */
    protected function authorizeCourseAccess(Course $course): void
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        if ($course->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($user->id === $course->lecturer_id || $user->hasRoleInTenant($tenant->id, ['admin'])) {
            return;
        }

        $hasSections = Section::where('course_id', $course->id)
            ->where('lecturer_id', $user->id)
            ->exists();

        if (! $hasSections) {
            abort(403);
        }
    }

    /**
     * Returns true when the user owns the course entirely (primary lecturer or admin),
     * false when they are a section-assigned lecturer only.
     */
    protected function isCourseOwner(Course $course): bool
    {
        $tenant = app('current_tenant');
        $user = auth()->user();

        return $user->id === $course->lecturer_id
            || $user->hasRoleInTenant($tenant->id, ['admin']);
    }
}
