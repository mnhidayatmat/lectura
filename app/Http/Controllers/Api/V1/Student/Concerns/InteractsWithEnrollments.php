<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student\Concerns;

use App\Models\Course;
use App\Models\Section;
use App\Models\SectionStudent;
use App\Models\User;
use Illuminate\Support\Collection;

trait InteractsWithEnrollments
{
    /**
     * Active section enrollments of the user within the current tenant.
     */
    protected function enrolledSectionIds(User $user): Collection
    {
        return SectionStudent::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereHas('section')
            ->pluck('section_id');
    }

    protected function enrolledCourseIds(User $user): Collection
    {
        return Section::whereIn('id', $this->enrolledSectionIds($user))
            ->pluck('course_id')
            ->unique()
            ->values();
    }

    protected function ensureEnrolled(Course $course, User $user): void
    {
        $enrolled = SectionStudent::whereIn('section_id', $course->sections()->pluck('id'))
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $enrolled) {
            abort(403, 'You are not enrolled in this course.');
        }
    }
}
