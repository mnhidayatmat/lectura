<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\AttendancePolicy;

class AttendancePolicyApiTest extends LecturerApiTestCase
{
    public function test_a_course_without_a_policy_returns_the_web_defaults(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/attendance-policy"))
            ->assertOk()
            ->assertJsonPath('data.exists', false)
            ->assertJsonPath('data.mode', 'percentage')
            ->assertJsonPath('data.warning_thresholds.1.label', 'Serious Warning')
            ->assertJsonPath('data.bar_threshold', null);
    }

    public function test_policy_can_be_saved_and_is_returned_sorted_by_level(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAsApi($lecturer)->putJson($this->tenantApi($tenant, "lecturer/courses/{$course->id}/attendance-policy"), [
            'mode' => 'count',
            'warning_thresholds' => [
                ['level' => 2, 'value' => 6, 'label' => 'Final warning'],
                ['level' => 1, 'value' => 3, 'label' => 'First warning'],
            ],
            'bar_threshold' => 8,
            'bar_action' => 'block',
            'include_late_as_absent' => true,
            'notify_student' => true,
            'notify_lecturer' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Attendance policy saved.')
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.warning_thresholds.0.label', 'First warning')
            ->assertJsonPath('data.bar_threshold', 8)
            ->assertJsonPath('data.notify_lecturer', false);

        $policy = AttendancePolicy::where('course_id', $course->id)->sole();
        $this->assertSame('count', $policy->mode);
        $this->assertTrue($policy->include_late_as_absent);
    }

    public function test_invalid_policies_and_outsiders_are_refused(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $stranger = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $path = $this->tenantApi($tenant, "lecturer/courses/{$course->id}/attendance-policy");

        $this->actingAsApi($lecturer)->putJson($path, ['mode' => 'weekly', 'warning_thresholds' => [], 'bar_action' => 'flag'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode', 'warning_thresholds']);

        $this->actingAsApi($stranger)->getJson($path)->assertForbidden();
    }
}
