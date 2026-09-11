<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\Attendance\QrCodeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentAttendanceApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function payloadFor(AttendanceSession $session, ?string $token = null): string
    {
        $qr = app(QrCodeService::class);

        return $qr->buildPayload(
            $session->id,
            $token ?? $qr->generateToken($session->qr_secret, $session->qr_rotation_seconds)
        );
    }

    public function test_checks_in_with_a_valid_qr_code(): void
    {
        [$tenant, $lecturer, $student, , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'student/attendance/check-in'), [
            'payload' => $this->payloadFor($session),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Checked in successfully!')
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.session.id', $session->id)
            ->assertJsonPath('data.session.course.code', 'SKMM1203')
            ->assertJsonPath('data.session.section_name', 'Section 01');

        $this->assertDatabaseHas('attendance_records', [
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'present',
            'method' => 'qr_scan',
        ]);
    }

    public function test_check_in_after_the_late_threshold_is_marked_late(): void
    {
        [$tenant, $lecturer, $student, , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer, ['started_at' => now()->subMinutes(20)]);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'student/attendance/check-in'), [
            'payload' => $this->payloadFor($session),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Checked in (late).')
            ->assertJsonPath('data.status', 'late');
    }

    public function test_second_scan_reports_already_checked_in(): void
    {
        [$tenant, $lecturer, $student, , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer);
        $url = $this->tenantApi($tenant, 'student/attendance/check-in');

        $this->actingAsApi($student)->postJson($url, ['payload' => $this->payloadFor($session)])->assertOk();

        $this->postJson($url, ['payload' => $this->payloadFor($session)])
            ->assertOk()
            ->assertJsonPath('message', 'You have already checked in.')
            ->assertJsonPath('data.status', 'present');

        $this->assertSame(1, AttendanceRecord::where('attendance_session_id', $session->id)->count());
    }

    public function test_rejects_invalid_expired_and_ended_sessions(): void
    {
        [$tenant, $lecturer, $student, , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer);
        $ended = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended']);
        $url = $this->tenantApi($tenant, 'student/attendance/check-in');

        $this->actingAsApi($student)->postJson($url, ['payload' => 'not-a-lectura-code'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid QR code.');

        $this->postJson($url, ['payload' => $this->payloadFor($session, 'expired-token')])
            ->assertStatus(422)
            ->assertJsonPath('message', 'QR code has expired. Please scan the latest code.');

        $this->postJson($url, ['payload' => $this->payloadFor($ended)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This attendance session has ended.');

        $this->postJson($url, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payload');
    }

    public function test_rejects_students_not_enrolled_in_the_section(): void
    {
        [$tenant, $lecturer, , , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer);
        $stranger = $this->createMember($tenant, 'student');

        $this->actingAsApi($stranger)->postJson($this->tenantApi($tenant, 'student/attendance/check-in'), [
            'payload' => $this->payloadFor($session),
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You are not enrolled in this section.');
    }

    public function test_lists_attendance_summaries_per_course(): void
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended']);
        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'absent',
            'method' => 'manual',
        ]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/attendance'))
            ->assertOk()
            ->assertJsonPath('data.0.course.id', $course->id)
            ->assertJsonPath('data.0.lecturer_name', 'Dr Hidayat')
            ->assertJsonPath('data.0.sections', ['Section 01'])
            ->assertJsonPath('data.0.summary.absent', 1)
            ->assertJsonPath('data.0.summary.total_sessions', 1)
            ->assertJsonPath('data.0.summary.attendance_rate', 0)
            ->assertJsonPath('data.0.summary.warning', null);
    }

    public function test_course_attendance_lists_sessions_with_excuse_eligibility(): void
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();
        $absent = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended', 'started_at' => now()->subDay()]);
        $record = AttendanceRecord::create([
            'attendance_session_id' => $absent->id,
            'user_id' => $student->id,
            'status' => 'absent',
            'method' => 'manual',
        ]);
        $this->createAttendanceSession($section, $lecturer, ['status' => 'ended', 'week_number' => 2]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/attendance/courses/'.$course->id))
            ->assertOk()
            ->assertJsonPath('data.course.code', 'SKMM1203')
            ->assertJsonPath('data.summary.total_sessions', 2)
            ->assertJsonCount(2, 'data.sessions')
            ->assertJsonPath('data.sessions.0.status', 'no_record')
            ->assertJsonPath('data.sessions.0.record', null)
            ->assertJsonPath('data.sessions.1.status', 'absent')
            ->assertJsonPath('data.sessions.1.record.id', $record->id)
            ->assertJsonPath('data.sessions.1.record.can_submit_excuse', true);
    }

    public function test_student_not_enrolled_cannot_view_course_attendance(): void
    {
        [$tenant, , , $course] = $this->enrolledStudent();
        $stranger = $this->createMember($tenant, 'student');

        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, 'student/attendance/courses/'.$course->id))
            ->assertForbidden();
    }

    public function test_submits_an_excuse_with_an_attachment(): void
    {
        Storage::fake('local');
        [$tenant, $lecturer, $student, , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended']);
        $record = AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'absent',
            'method' => 'manual',
        ]);

        $this->actingAsApi($student)->post($this->tenantApi($tenant, "student/attendance/records/{$record->id}/excuse"), [
            'reason' => 'Fever, clinic visit',
            'category' => 'medical',
            'attachment' => UploadedFile::fake()->create('mc.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('message', 'Your excuse has been submitted for review.')
            ->assertJsonPath('data.excuse.status', 'pending')
            ->assertJsonPath('data.excuse.category_label', 'Medical')
            ->assertJsonPath('data.excuse.attachment_filename', 'mc.pdf');

        $this->assertDatabaseHas('attendance_excuses', [
            'attendance_record_id' => $record->id,
            'user_id' => $student->id,
            'status' => 'pending',
        ]);
    }

    public function test_excuse_rules_are_enforced(): void
    {
        [$tenant, $lecturer, $student, , $section] = $this->enrolledStudent();
        $session = $this->createAttendanceSession($section, $lecturer, ['status' => 'ended']);
        $present = AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'user_id' => $student->id,
            'status' => 'present',
            'checked_in_at' => now(),
        ]);
        $classmate = $this->createMember($tenant, 'student');
        $this->enroll($section, $classmate);
        $classmateRecord = AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'user_id' => $classmate->id,
            'status' => 'absent',
            'method' => 'manual',
        ]);
        $valid = ['reason' => 'Family matter', 'category' => 'family_emergency'];

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "student/attendance/records/{$present->id}/excuse"), $valid)
            ->assertStatus(422)
            ->assertJsonPath('message', 'You can only submit excuses for absent records.');

        $this->postJson($this->tenantApi($tenant, "student/attendance/records/{$classmateRecord->id}/excuse"), $valid)
            ->assertForbidden();

        $this->actingAsApi($classmate)->postJson($this->tenantApi($tenant, "student/attendance/records/{$classmateRecord->id}/excuse"), ['category' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->postJson($this->tenantApi($tenant, "student/attendance/records/{$classmateRecord->id}/excuse"), $valid)
            ->assertCreated();

        $this->postJson($this->tenantApi($tenant, "student/attendance/records/{$classmateRecord->id}/excuse"), $valid)
            ->assertStatus(422)
            ->assertJsonPath('message', 'An excuse has already been submitted for this session.');
    }
}
