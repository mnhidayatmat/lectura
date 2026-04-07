<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendancePolicyController extends Controller
{
    use AuthorizesCourseAccess;
    /**
     * Show policy edit form.
     */
    public function edit(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourse($course);

        $policy = $course->attendancePolicy ?? new AttendancePolicy([
            'mode' => 'percentage',
            'warning_thresholds' => [
                ['level' => 1, 'value' => 20, 'label' => 'Warning'],
                ['level' => 2, 'value' => 40, 'label' => 'Serious Warning'],
            ],
            'bar_threshold' => null,
            'bar_action' => 'flag',
            'include_late_as_absent' => false,
            'notify_student' => true,
            'notify_lecturer' => true,
        ]);

        return view('tenant.attendance.policy.edit', compact('course', 'policy'));
    }

    /**
     * Save policy.
     */
    public function update(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $request->validate([
            'mode' => ['required', 'in:percentage,count'],
            'warning_thresholds' => ['required', 'array', 'min:1', 'max:5'],
            'warning_thresholds.*.level' => ['required', 'integer', 'min:1', 'max:5'],
            'warning_thresholds.*.value' => ['required', 'numeric', 'min:1', 'max:100'],
            'warning_thresholds.*.label' => ['required', 'string', 'max:50'],
            'bar_threshold' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'bar_action' => ['required', 'in:flag,notify,block'],
            'include_late_as_absent' => ['nullable'],
            'notify_student' => ['nullable'],
            'notify_lecturer' => ['nullable'],
        ]);

        $tenant = app('current_tenant');

        // Filter valid thresholds
        $thresholds = collect($request->warning_thresholds)
            ->filter(fn ($t) => !empty($t['value']) && !empty($t['label']))
            ->sortBy('level')
            ->values()
            ->toArray();

        AttendancePolicy::updateOrCreate(
            ['course_id' => $course->id],
            [
                'tenant_id' => $tenant->id,
                'mode' => $request->mode,
                'warning_thresholds' => $thresholds,
                'bar_threshold' => $request->bar_threshold,
                'bar_action' => $request->bar_action,
                'include_late_as_absent' => (bool) $request->include_late_as_absent,
                'notify_student' => (bool) $request->notify_student,
                'notify_lecturer' => (bool) $request->notify_lecturer,
            ],
        );

        return redirect()->route('tenant.courses.show', [$tenantSlug, $course])
            ->with('success', 'Attendance policy saved.');
    }

    protected function authorizeCourse(Course $course): void
    {
        $this->authorizeCourseAccess($course);
    }
}
