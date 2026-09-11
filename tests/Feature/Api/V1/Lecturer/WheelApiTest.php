<?php

namespace Tests\Feature\Api\V1\Lecturer;

class WheelApiTest extends LecturerApiTestCase
{
    public function test_index_lists_courses_sections_and_latest_session_defaults(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'SKM1001']);
        $section = $this->createSection($course);
        $this->startAttendance($section, $lecturer, ['status' => 'ended', 'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)]);
        $latest = $this->startAttendance($section, $lecturer, ['started_at' => now()->subHour()]);

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/wheel'))
            ->assertOk()
            ->assertJsonPath('data.courses.0.id', $course->id)
            ->assertJsonPath('data.courses.0.sections.0.id', $section->id)
            ->assertJsonPath('data.defaults.course_id', $course->id)
            ->assertJsonPath('data.defaults.section_id', $section->id)
            ->assertJsonPath('data.defaults.session_id', $latest->id);
    }

    public function test_sessions_lists_recent_sessions_for_a_section(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer));
        $student = $this->createMember($tenant);
        $this->enroll($section, $student);
        $this->startAttendance($section, $lecturer, ['status' => 'ended', 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()]);
        $active = $this->startAttendance($section, $lecturer);
        $this->markAttendance($active, $student);

        $response = $this->actingAsApi($lecturer)
            ->getJson($this->tenantApi($tenant, "lecturer/wheel/sessions?section_id={$section->id}"))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.0.checked_in', 1)
            ->assertJsonPath('data.1.is_active', false);

        $this->assertStringStartsWith('LIVE — W3 Lecture', $response->json('data.0.label'));
    }

    public function test_present_students_can_include_late_students(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer, ['code' => 'SKM2002']));
        $session = $this->startAttendance($section, $lecturer);
        $this->markAttendance($session, $this->createMember($tenant), 'present');
        $this->markAttendance($session, $this->createMember($tenant), 'late');
        $this->markAttendance($session, $this->createMember($tenant), 'absent');

        $url = $this->tenantApi($tenant, "lecturer/wheel/present-students?session_id={$session->id}");

        $this->actingAsApi($lecturer)->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.session.section_name', 'Section 01')
            ->assertJsonPath('data.session.course_code', 'SKM2002');

        $this->getJson($url.'&include_late=1')
            ->assertOk()
            ->assertJsonCount(2, 'data.students');
    }

    public function test_unrelated_lecturer_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $section = $this->createSection($this->createCourse($tenant, $lecturer));
        $session = $this->startAttendance($section, $lecturer);
        $outsider = $this->createMember($tenant, 'lecturer');

        $this->actingAsApi($outsider)
            ->getJson($this->tenantApi($tenant, "lecturer/wheel/sessions?section_id={$section->id}"))
            ->assertForbidden();

        $this->getJson($this->tenantApi($tenant, "lecturer/wheel/present-students?session_id={$session->id}"))
            ->assertForbidden();
    }

    public function test_wheel_parameters_are_required(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/wheel/sessions'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('section_id');

        $this->getJson($this->tenantApi($tenant, 'lecturer/wheel/present-students'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('session_id');
    }
}
