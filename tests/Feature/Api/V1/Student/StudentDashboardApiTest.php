<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\StudentMark;
use App\Notifications\AttendanceWarningNotification;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentDashboardApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_dashboard_summarises_courses_sessions_deadlines_and_marks(): void
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();

        $session = $this->createAttendanceSession($section, $lecturer);
        $this->createAssignment($course, $lecturer, ['title' => 'Problem Set 1', 'deadline' => now()->addDay()]);
        $this->createAssessment($course, [
            'title' => 'Project Report',
            'requires_submission' => true,
            'due_date' => now()->addDays(3),
        ]);

        $quiz = $this->createAssignment($course, $lecturer, ['title' => 'Quiz 1', 'status' => 'closed', 'deadline' => now()->subDay()]);
        StudentMark::create([
            'tenant_id' => $tenant->id,
            'assignment_id' => $quiz->id,
            'user_id' => $student->id,
            'total_marks' => 18,
            'max_marks' => 20,
            'percentage' => 90,
            'grade' => 'A',
            'is_final' => true,
            'finalized_at' => now(),
        ]);

        $student->notify(new AttendanceWarningNotification($course, 1, 'Warning 1', 20.0, 3));

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.courses_count', 1)
            ->assertJsonPath('data.courses.0.code', 'SKMM1203')
            ->assertJsonPath('data.courses.0.lecturer_name', 'Dr Hidayat')
            ->assertJsonPath('data.active_attendance_sessions.0.id', $session->id)
            ->assertJsonPath('data.active_attendance_sessions.0.section_name', 'Section 01')
            ->assertJsonPath('data.active_attendance_sessions.0.my_status', null)
            ->assertJsonCount(2, 'data.upcoming_deadlines')
            ->assertJsonPath('data.upcoming_deadlines.0.kind', 'assignment')
            ->assertJsonPath('data.upcoming_deadlines.0.submitted', false)
            ->assertJsonPath('data.upcoming_deadlines.1.kind', 'assessment')
            ->assertJsonPath('data.recent_marks.0.title', 'Quiz 1')
            ->assertJsonPath('data.recent_marks.0.percentage', 90)
            ->assertJsonPath('data.unread_notifications_count', 1);
    }

    public function test_dashboard_is_empty_for_a_student_without_enrollments(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/dashboard'))
            ->assertOk()
            ->assertJsonPath('data.courses_count', 0)
            ->assertJsonPath('data.courses', [])
            ->assertJsonPath('data.active_attendance_sessions', [])
            ->assertJsonPath('data.upcoming_deadlines', [])
            ->assertJsonPath('data.recent_marks', []);
    }

    public function test_dashboard_requires_membership_of_the_institution(): void
    {
        [$tenant] = $this->enrolledStudent();
        $outsider = $this->createMember($this->createTenant(), 'student');

        $this->actingAsApi($outsider)->getJson($this->tenantApi($tenant, 'student/dashboard'))
            ->assertForbidden();
    }
}
