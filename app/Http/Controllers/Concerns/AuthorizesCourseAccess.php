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

        if ($user->hasRoleInTenant($tenant->id, ['admin'])) {
            return;
        }

        if ($user->id === $course->lecturer_id) {
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
     * Returns true when the user is a tenant admin.
     */
    protected function isTenantAdmin(): bool
    {
        return auth()->user()->hasRoleInTenant(app('current_tenant')->id, ['admin']);
    }

    /**
     * Returns true when the user owns the course entirely (primary lecturer or admin),
     * false when they are a section-assigned lecturer only.
     */
    protected function isCourseOwner(Course $course): bool
    {
        return auth()->id() === $course->lecturer_id || $this->isTenantAdmin();
    }

    /**
     * Get the sections of a course that the current user may access.
     *
     * - Admins see all sections.
     * - Course owner sees sections assigned to them OR unassigned (lecturer_id IS NULL).
     * - Section-assigned lecturer sees only sections assigned to them.
     */
    protected function lecturerSections(Course $course): Builder
    {
        $query = $course->sections();
        $userId = auth()->id();

        if ($this->isTenantAdmin()) {
            return $query;
        }

        if ($userId === $course->lecturer_id) {
            // Course owner: own sections + unassigned sections
            $query->where(function ($q) use ($userId) {
                $q->where('lecturer_id', $userId)
                  ->orWhereNull('lecturer_id');
            });
        } else {
            // Section-assigned lecturer: only their sections
            $query->where('lecturer_id', $userId);
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
     * Get all section IDs the current user may access across all courses.
     */
    protected function allAccessibleSectionIds(): Collection
    {
        $userId = auth()->id();

        if ($this->isTenantAdmin()) {
            return Section::pluck('id');
        }

        // Sections explicitly assigned to user
        $assignedSectionIds = Section::where('lecturer_id', $userId)->pluck('id');

        // Unassigned sections in courses the user owns
        $ownedCourseIds = Course::where('lecturer_id', $userId)->pluck('id');
        $unassignedSectionIds = Section::whereIn('course_id', $ownedCourseIds)
            ->whereNull('lecturer_id')
            ->pluck('id');

        return $assignedSectionIds->merge($unassignedSectionIds)->unique();
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
