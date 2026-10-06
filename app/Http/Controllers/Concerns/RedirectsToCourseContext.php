<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

trait RedirectsToCourseContext
{
    /**
     * Flat feature index pages redirect into the course-scoped page when a
     * course context is active; otherwise they fall back to the course picker.
     */
    protected function redirectToCourseContext(string $courseRouteName): ?RedirectResponse
    {
        $course = app()->bound('current_course') ? app('current_course') : null;

        if (! $course) {
            return null;
        }

        return redirect()->route($courseRouteName, [
            'tenant' => app('current_tenant')->slug,
            'course' => $course->id,
        ]);
    }

    protected function fallbackToCoursePicker(): RedirectResponse
    {
        return redirect()->route('tenant.courses.index', app('current_tenant')->slug);
    }
}
