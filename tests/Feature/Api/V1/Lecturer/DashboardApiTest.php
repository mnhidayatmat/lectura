<?php

namespace Tests\Feature\Api\V1\Lecturer;

class DashboardApiTest extends LecturerApiTestCase
{
    public function test_dashboard_matches_web_stats_schedule_and_active_sessions(): void
    {
        $this->travelTo(now()->startOfWeek()->setTime(10, 30));

        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'SKM3013']);
        $this->createCourse($tenant, $lecturer, ['status' => 'draft']);
        $section = $this->createSection($course, ['schedule' => [
            ['day' => 'monday', 'start_time' => '14:00', 'end_time' => '16:00', 'location' => 'BK1', 'type' => 'lab'],
            ['day' => 'monday', 'start_time' => '08:00', 'end_time' => '09:00', 'type' => 'lecture'],
            ['day' => 'monday', 'start_time' => '10:00', 'end_time' => '12:00', 'type' => 'tutorial'],
            ['day' => 'tuesday', 'start_time' => '10:00', 'end_time' => '12:00', 'type' => 'lecture'],
        ]]);
        $aina = $this->createMember($tenant);
        $zul = $this->createMember($tenant);
        $this->enroll($section, $aina);
        $this->enroll($section, $zul);

        $ended = $this->startAttendance($section, $lecturer, ['status' => 'ended', 'ended_at' => now()->subDay(), 'started_at' => now()->subDay()]);
        $this->markAttendance($ended, $aina, 'present');
        $this->markAttendance($ended, $zul, 'absent');
        $active = $this->startAttendance($section, $lecturer);

        $colleague = $this->createMember($tenant, 'lecturer');
        $this->enroll($this->createSection($this->createCourse($tenant, $colleague)), $this->createMember($tenant));

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.day', 'Monday')
            ->assertJsonPath('data.stats.active_courses', 1)
            ->assertJsonPath('data.stats.students', 2)
            ->assertJsonPath('data.stats.avg_attendance', 50)
            ->assertJsonCount(3, 'data.today_schedule')
            ->assertJsonPath('data.today_schedule.0.start_time', '08:00')
            ->assertJsonPath('data.today_schedule.0.is_past', true)
            ->assertJsonPath('data.today_schedule.1.is_now', true)
            ->assertJsonPath('data.today_schedule.1.course.code', 'SKM3013')
            ->assertJsonPath('data.today_schedule.2.location', 'BK1')
            ->assertJsonPath('data.today_schedule.2.is_now', false)
            ->assertJsonCount(1, 'data.active_sessions')
            ->assertJsonPath('data.active_sessions.0.id', $active->id)
            ->assertJsonPath('data.active_sessions.0.total_students', 2)
            ->assertJsonCount(2, 'data.recent_courses');
    }

    public function test_student_role_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'lecturer/dashboard'))
            ->assertForbidden()
            ->assertJsonPath('message', 'This area is for lecturers.');
    }

    public function test_multi_role_user_follows_the_active_role_header(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createMember($tenant, 'lecturer');
        $this->addRole($tenant, $user, 'student');

        $this->actingAsApi($user, 'student')->getJson($this->tenantApi($tenant, 'lecturer/dashboard'))
            ->assertForbidden();

        $this->actingAsApi($user, 'lecturer')->getJson($this->tenantApi($tenant, 'lecturer/dashboard'))
            ->assertOk();
    }
}
