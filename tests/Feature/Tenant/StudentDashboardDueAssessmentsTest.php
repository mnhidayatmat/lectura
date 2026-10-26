<?php

namespace Tests\Feature\Tenant;

use App\Models\AssessmentSubmission;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\StudentMark;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * The student dashboard opens with the assessments still to hand in, each
 * coloured by how close its due date is, and submitted ones in green.
 */
class StudentDashboardDueAssessmentsTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_due_assessments_are_highlighted_and_graded_by_urgency(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $this->createAssessment($course, ['title' => 'Lab 1 report', 'requires_submission' => true, 'due_date' => now()->addHours(20)]);
        $this->createAssessment($course, ['title' => 'Assignment 1', 'requires_submission' => true, 'due_date' => now()->addDays(4)]);
        $this->createAssessment($course, ['title' => 'Project report', 'requires_submission' => true, 'due_date' => now()->addDays(20)]);
        $done = $this->createAssessment($course, ['title' => 'Quiz upload', 'requires_submission' => true, 'due_date' => now()->addDays(3)]);
        $this->createAssessment($course, ['title' => 'Old essay', 'requires_submission' => true, 'due_date' => now()->subDay()]);
        $this->createAssessment($course, ['title' => 'Final exam', 'due_date' => now()->addDays(2)]);
        AssessmentSubmission::create([
            'tenant_id' => $tenant->id,
            'assessment_id' => $done->id,
            'user_id' => $student->id,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($student)->get("/{$tenant->slug}/dashboard")->assertOk();

        $items = $response->viewData('dueAssessments');
        $this->assertSame(
            ['Lab 1 report' => 'urgent', 'Quiz upload' => 'submitted', 'Assignment 1' => 'soon', 'Project report' => 'later'],
            $items->mapWithKeys(fn ($item) => [$item['assessment']->title => $item['tone']])->all(),
        );
        $response->assertSeeInOrder(['Welcome back', 'Assessments due', '3 to submit', 'Lab 1 report', 'Due soon', 'Quiz upload', 'Submitted'])
            ->assertDontSee('Old essay')
            ->assertDontSee('Final exam');
    }

    public function test_the_block_is_hidden_when_nothing_is_due(): void
    {
        [$tenant, , $student] = $this->enrolledStudent();

        $this->actingAs($student)->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertDontSee('Assessments due');
    }

    public function test_it_shows_open_check_ins_attendance_and_released_marks(): void
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();

        $ended = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended', 'started_at' => now()->subWeek()]);
        $this->createAttendanceSession($section, $lecturer, ['status' => 'ended', 'started_at' => now()->subDays(3)]);
        AttendanceRecord::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $ended->id,
            'user_id' => $student->id,
            'status' => 'absent',
        ]);
        $this->createAttendanceSession($section, $lecturer, ['week_number' => 3]);

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

        $response = $this->actingAs($student)->get("/{$tenant->slug}/dashboard")->assertOk();

        $this->assertEquals(50, $response->viewData('attendanceRate'));
        $this->assertSame(['SKMM1203'], $response->viewData('lowAttendanceCourses')->pluck('code')->all());
        $response->assertSee('Low in SKMM1203')
            ->assertSee('Attendance is open · SKMM1203')
            ->assertSee('Week 3')
            ->assertSeeInOrder(['Recent marks', 'Quiz 1', '90%', '18 / 20 · A'])
            ->assertSeeInOrder(['My courses', 'SKMM1203', '50%']);
    }

    public function test_answered_check_ins_are_not_flagged(): void
    {
        [$tenant, $lecturer, $student, , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer);
        AttendanceRecord::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'present',
        ]);

        $this->actingAs($student)->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertDontSee('Attendance is open')
            ->assertSee('No marks released yet');
    }

    public function test_low_attendance_follows_the_course_bar_threshold(): void
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();
        AttendancePolicy::create(['tenant_id' => $tenant->id, 'course_id' => $course->id, 'mode' => 'percentage', 'warning_thresholds' => [], 'bar_threshold' => 40]);

        foreach (['present', 'absent'] as $status) {
            $session = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended']);
            AttendanceRecord::create([
                'tenant_id' => $tenant->id,
                'attendance_session_id' => $session->id,
                'user_id' => $student->id,
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($student)->get("/{$tenant->slug}/dashboard")->assertOk();

        $this->assertTrue($response->viewData('lowAttendanceCourses')->isEmpty());
        $response->assertSee('Across all courses')->assertDontSee('Low in');
    }
}
