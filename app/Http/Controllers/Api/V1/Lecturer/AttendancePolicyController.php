<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendancePolicyController extends Controller
{
    use AuthorizesLecturerAccess;

    public function show(Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        $policy = $course->attendancePolicy;

        return response()->json(['data' => $this->present($policy ?? $this->defaults(), exists: $policy !== null)]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);

        // Same rules as AttendancePolicyController@update on the web.
        $request->validate([
            'mode' => ['required', 'in:percentage,count'],
            'warning_thresholds' => ['required', 'array', 'min:1', 'max:5'],
            'warning_thresholds.*.level' => ['required', 'integer', 'min:1', 'max:5'],
            'warning_thresholds.*.value' => ['required', 'numeric', 'min:1', 'max:100'],
            'warning_thresholds.*.label' => ['required', 'string', 'max:50'],
            'bar_threshold' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'bar_action' => ['required', 'in:flag,notify,block'],
            'include_late_as_absent' => ['boolean'],
            'notify_student' => ['boolean'],
            'notify_lecturer' => ['boolean'],
        ]);

        $thresholds = collect($request->input('warning_thresholds'))
            ->sortBy('level')
            ->map(fn (array $t) => ['level' => (int) $t['level'], 'value' => $t['value'] + 0, 'label' => $t['label']])
            ->values()
            ->all();

        $policy = AttendancePolicy::updateOrCreate(
            ['course_id' => $course->id],
            [
                'tenant_id' => app('current_tenant')->id,
                'mode' => $request->input('mode'),
                'warning_thresholds' => $thresholds,
                'bar_threshold' => $request->input('bar_threshold'),
                'bar_action' => $request->input('bar_action'),
                'include_late_as_absent' => $request->boolean('include_late_as_absent'),
                'notify_student' => $request->boolean('notify_student'),
                'notify_lecturer' => $request->boolean('notify_lecturer'),
            ],
        );

        return response()->json([
            'message' => 'Attendance policy saved.',
            'data' => $this->present($policy->refresh(), exists: true),
        ]);
    }

    /** The web form's starting values for a course without a policy. */
    private function defaults(): AttendancePolicy
    {
        return new AttendancePolicy([
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
    }

    private function present(AttendancePolicy $policy, bool $exists): array
    {
        return [
            'exists' => $exists,
            'mode' => $policy->mode,
            'warning_thresholds' => collect($policy->warning_thresholds ?? [])->map(fn (array $t) => [
                'level' => (int) $t['level'],
                'value' => (float) $t['value'],
                'label' => (string) $t['label'],
            ])->values(),
            'bar_threshold' => $policy->bar_threshold !== null ? (float) $policy->bar_threshold : null,
            'bar_action' => $policy->bar_action,
            'include_late_as_absent' => (bool) $policy->include_late_as_absent,
            'notify_student' => (bool) $policy->notify_student,
            'notify_lecturer' => (bool) $policy->notify_lecturer,
        ];
    }
}
