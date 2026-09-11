<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AttendanceRecord;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentCourseApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    public function test_lists_enrolled_courses_only(): void
    {
        [$tenant, , $student, $course, $section] = $this->enrolledStudent();
        $other = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'), ['code' => 'ZZZ9999']);
        $this->createSection($other, ['code' => '02']);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/courses'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $course->id)
            ->assertJsonPath('data.0.lecturer_name', 'Dr Hidayat')
            ->assertJsonPath('data.0.sections.0.id', $section->id);
    }

    public function test_shows_course_detail_with_my_sections_and_attendance(): void
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();
        $section->update(['schedule' => [
            ['day' => 'monday', 'start_time' => '08:00', 'end_time' => '10:00', 'location' => 'BK1', 'type' => 'lecture'],
        ]]);

        $session = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended']);
        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'present',
            'checked_in_at' => now(),
        ]);
        $this->createAssignment($course, $lecturer);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/courses/'.$course->id))
            ->assertOk()
            ->assertJsonPath('data.course.code', 'SKMM1203')
            ->assertJsonPath('data.course.lecturer.name', 'Dr Hidayat')
            ->assertJsonPath('data.my_sections.0.schedule.0.location', 'BK1')
            ->assertJsonPath('data.my_sections.0.lecturers.0.name', 'Dr Hidayat')
            ->assertJsonPath('data.attendance_summary.present', 1)
            ->assertJsonPath('data.attendance_summary.rate', 100)
            ->assertJsonCount(1, 'data.upcoming_assignments')
            ->assertJsonPath('data.counts.upcoming_assignments', 1);
    }

    public function test_student_not_enrolled_cannot_view_the_course(): void
    {
        [$tenant, $lecturer, , $course] = $this->enrolledStudent();
        $stranger = $this->createMember($tenant, 'student');

        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, 'student/courses/'.$course->id))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not enrolled in this course.');
    }

    public function test_course_from_another_institution_is_not_found(): void
    {
        [$tenant, , $student] = $this->enrolledStudent();
        $otherTenant = $this->createTenant();
        $foreign = $this->createCourse($otherTenant, $this->createMember($otherTenant, 'lecturer'));

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/courses/'.$foreign->id))
            ->assertNotFound();
    }

    public function test_enrolls_with_a_section_invite_code(): void
    {
        [$tenant, $lecturer, $student] = $this->enrolledStudent();
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'SKMM2323']);
        $section = $this->createSection($course, ['name' => 'Section 05', 'invite_code' => 'ABCD1234']);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'student/courses/enroll'), ['invite_code' => 'abcd-1234'])
            ->assertOk()
            ->assertJsonPath('message', 'Successfully enrolled in SKMM2323 — Section 05!')
            ->assertJsonPath('data.course.id', $course->id)
            ->assertJsonPath('data.already_enrolled', false);

        $this->assertDatabaseHas('section_students', [
            'section_id' => $section->id,
            'user_id' => $student->id,
            'enrollment_method' => 'invite_code',
            'is_active' => true,
        ]);
    }

    public function test_enroll_reports_an_existing_enrollment(): void
    {
        [$tenant, , $student, , $section] = $this->enrolledStudent();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'student/courses/enroll'), ['invite_code' => $section->invite_code])
            ->assertOk()
            ->assertJsonPath('data.already_enrolled', true)
            ->assertJsonPath('message', 'You are already enrolled in SKMM1203 — Section 01.');
    }

    public function test_enroll_rejects_course_codes_and_unknown_codes(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'student/courses/enroll'), ['invite_code' => $course->invite_code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invite_code' => 'That code belongs to a course, not a section.']);

        $this->postJson($this->tenantApi($tenant, 'student/courses/enroll'), ['invite_code' => 'NOPE0000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invite_code' => 'Invalid invite code. Please check with your lecturer.']);

        $this->postJson($this->tenantApi($tenant, 'student/courses/enroll'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('invite_code');
    }

    public function test_enroll_rejects_full_and_inactive_sections(): void
    {
        [$tenant, $lecturer, $student] = $this->enrolledStudent();
        $course = $this->createCourse($tenant, $lecturer);
        $full = $this->createSection($course, ['capacity' => 1]);
        $this->enroll($full, $this->createMember($tenant, 'student'));
        $closed = $this->createSection($course, ['code' => '03', 'is_active' => false]);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'student/courses/enroll'), ['invite_code' => $full->invite_code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invite_code' => 'This section is full. Please contact your lecturer.']);

        $this->postJson($this->tenantApi($tenant, 'student/courses/enroll'), ['invite_code' => $closed->invite_code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invite_code' => 'This section is not accepting enrollments.']);
    }
}
