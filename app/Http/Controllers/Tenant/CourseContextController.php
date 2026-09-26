<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Course\CourseContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourseContextController extends Controller
{
    public function __construct(
        protected CourseContextService $courseContext,
    ) {}

    public function select(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = app('current_tenant');

        $validated = $request->validate([
            'course_id' => ['required', 'integer'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ]);

        // BelongsToTenant scopes the lookup to the current tenant; anything
        // else is a 404, and CourseContextService enforces member access (403).
        $course = Course::findOrFail((int) $validated['course_id']);

        $this->courseContext->set($request->user(), $tenant, $course);

        $redirect = $this->safeRedirect($validated['redirect'] ?? null, $tenant->slug, $course);

        return redirect($redirect);
    }

    public function clear(string $tenantSlug): RedirectResponse
    {
        $this->courseContext->clear(app('current_tenant'));

        return redirect()->route('tenant.courses.index', $tenantSlug);
    }

    /**
     * Only relative, same-host paths are allowed as redirect targets so the
     * select endpoint can't be used as an open redirect.
     */
    protected function safeRedirect(?string $target, string $tenantSlug, Course $course): string
    {
        if ($target && str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_starts_with($target, '/\\')) {
            return $target;
        }

        return route('tenant.courses.show', ['tenant' => $tenantSlug, 'course' => $course->id]);
    }
}
