<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use App\Services\Course\CourseContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseContextController extends Controller
{
    public function __construct(
        protected CourseContextService $courseContext,
    ) {}

    /**
     * The full-screen "who are you teaching today?" gate.
     */
    public function picker(Request $request, string $tenantSlug): View|RedirectResponse
    {
        $tenant = app('current_tenant');

        if ($request->user()->roleInTenant($tenant->id) === 'student') {
            return redirect()->route('tenant.dashboard', $tenant->slug);
        }

        $courses = $this->courseContext->accessibleCourses($request->user(), $tenant)
            ->reject(fn (Course $course) => $course->status === 'archived')
            ->values();

        $todaySlots = $this->todaySlotsByCourse($courses);

        $studentCounts = DB::table('section_students')
            ->join('sections', 'sections.id', '=', 'section_students.section_id')
            ->whereIn('sections.course_id', $courses->pluck('id'))
            ->where('section_students.is_active', true)
            ->groupBy('sections.course_id')
            ->selectRaw('sections.course_id, count(distinct section_students.user_id) as total')
            ->pluck('total', 'course_id');

        $terms = $courses->mapWithKeys(fn (Course $course) => [$course->id => $this->courseContext->termFor($course)]);

        // Classes today first (earliest first), then courses running this semester.
        $courses = $courses->sortBy(fn (Course $course) => [
            $todaySlots[$course->id]->isEmpty() ? 1 : 0,
            $todaySlots[$course->id]->first()->start_time ?? '',
            $terms[$course->id]?->isCurrent() ? 0 : 1,
            $course->code,
        ])->values();

        return view('tenant.course-context.picker', [
            'tenant' => $tenant,
            'courses' => $courses,
            'todaySlots' => $todaySlots,
            'studentCounts' => $studentCounts,
            'terms' => $terms,
            'currentCourseId' => app()->bound('current_course') ? app('current_course')->id : null,
        ]);
    }

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

        return redirect()->route('tenant.course-context.picker', $tenantSlug);
    }

    /**
     * Only relative, same-host paths are allowed as redirect targets so the
     * select endpoint can't be used as an open redirect. The default is the
     * course overview.
     */
    protected function safeRedirect(?string $target, string $tenantSlug, Course $course): string
    {
        if ($target && str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_starts_with($target, '/\\')) {
            return $target;
        }

        return route('tenant.courses.show', ['tenant' => $tenantSlug, 'course' => $course->id]);
    }

    /**
     * Today's class slots per course id, for the picker badges.
     */
    protected function todaySlotsByCourse(Collection $courses): Collection
    {
        $today = strtolower(now()->format('l'));

        return $courses->mapWithKeys(fn (Course $course) => [
            $course->id => $course->sections
                ->where('is_active', true)
                ->flatMap(fn (Section $section) => collect($section->schedule ?? [])
                    ->filter(fn ($slot) => ($slot['day'] ?? '') === $today)
                    ->map(fn ($slot) => (object) [
                        'section_name' => $section->name,
                        'start_time' => $slot['start_time'] ?? '',
                        'type' => $slot['type'] ?? 'lecture',
                    ]))
                ->sortBy('start_time')
                ->values(),
        ]);
    }
}
