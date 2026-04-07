<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    /**
     * Get the sections of a course that the current user may access.
     * Course owners / admins see all sections; section-assigned lecturers see only theirs.
     */
    protected function lecturerSections(Course $course): Builder
    {
        $query = $course->sections();

        if (! $this->isCourseOwner($course)) {
            $query->where('lecturer_id', auth()->id());
        }

        return $query;
    }

    /**
     * Get section IDs the current user may access for a given course.
     */
    protected function lecturerSectionIds(Course $course): Collection
    {
        return $this->lecturerSections($course)->pluck('id');
    }

    /**
     * Get all course IDs the current user can access (owned + section-assigned).
     */
    protected function accessibleCourseIds(): Collection
    {
        $userId = auth()->id();
        $owned = Course::where('lecturer_id', $userId)->pluck('id');
        $fromSections = Section::where('lecturer_id', $userId)->pluck('course_id');

        return $owned->merge($fromSections)->unique();
    }
}
