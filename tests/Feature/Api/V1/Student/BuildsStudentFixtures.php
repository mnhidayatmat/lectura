<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

trait BuildsStudentFixtures
{
    /**
     * @return array{0: Tenant, 1: User, 2: User, 3: Course, 4: Section}
     */
    protected function enrolledStudent(): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer', ['name' => 'Dr Hidayat']);
        $student = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'SKMM1203', 'title' => 'Statics']);
        $section = $this->createSection($course, [], [$lecturer]);
        $this->enroll($section, $student);

        return [$tenant, $lecturer, $student, $course, $section];
    }

    protected function createAttendanceSession(Section $section, User $lecturer, array $attributes = []): AttendanceSession
    {
        return AttendanceSession::create(array_merge([
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'lecturer_id' => $lecturer->id,
            'session_type' => 'lecture',
            'week_number' => 1,
            'qr_secret' => Str::random(64),
            'qr_mode' => 'rotating',
            'qr_rotation_seconds' => 30,
            'late_threshold_minutes' => 15,
            'status' => 'active',
            'started_at' => now(),
        ], $attributes));
    }

    protected function createAssignment(Course $course, User $lecturer, array $attributes = []): Assignment
    {
        return Assignment::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'created_by' => $lecturer->id,
            'title' => 'Problem Set 1',
            'type' => 'individual',
            'total_marks' => 20,
            'status' => 'published',
            'deadline' => now()->addDays(2),
        ], $attributes));
    }

    protected function createAssessment(Course $course, array $attributes = []): Assessment
    {
        return Assessment::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'title' => 'Mid-term Test',
            'type' => 'test',
            'weightage' => 20,
            'total_marks' => 50,
            'status' => 'active',
            'requires_submission' => false,
        ], $attributes));
    }
}
