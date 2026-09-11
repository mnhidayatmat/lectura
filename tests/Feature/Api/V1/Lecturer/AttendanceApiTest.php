<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Attendance\QrCodeService;

class AttendanceApiTest extends LecturerApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    private Section $section;

    private User $aina;

    private User $zul;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'SKM3013']);
        $this->section = $this->createSection($this->course);
        $this->aina = $this->createMember($this->tenant, 'student', ['name' => 'Aina Sofea']);
        $this->zul = $this->createMember($this->tenant, 'student', ['name' => 'Zul Hakim']);
        $this->enroll($this->section, $this->aina);
        $this->enroll($this->section, $this->zul);
    }

    private function api(string $path = ''): string
    {
        return $this->tenantApi($this->tenant, 'lecturer/attendance'.$path);
    }

    public function test_index_returns_active_recent_and_startable_sections(): void
    {
        $active = $this->startAttendance($this->section, $this->lecturer);
        $this->markAttendance($active, $this->aina);
        $ended = $this->startAttendance($this->section, $this->lecturer, ['status' => 'ended', 'started_at' => now()->subDay(), 'ended_at' => now()->subDay()]);
        $this->markAttendance($ended, $this->aina, 'late');
        $this->markAttendance($ended, $this->zul, 'absent');

        $this->actingAsApi($this->lecturer)->getJson($this->api())
            ->assertOk()
            ->assertJsonPath('data.active_sessions.0.id', $active->id)
            ->assertJsonPath('data.active_sessions.0.course.code', 'SKM3013')
            ->assertJsonPath('data.active_sessions.0.counts.checked_in', 1)
            ->assertJsonPath('data.active_sessions.0.total_students', 2)
            ->assertJsonPath('data.recent_sessions.0.id', $ended->id)
            ->assertJsonPath('data.recent_sessions.0.counts.late', 1)
            ->assertJsonPath('data.recent_sessions.0.counts.absent', 1)
            ->assertJsonPath('data.sections.0.id', $this->section->id)
            ->assertJsonPath('data.sections.0.active_session_id', $active->id)
            ->assertJsonPath('data.session_types', ['lecture', 'tutorial', 'lab', 'extra', 'replacement']);
    }

    public function test_start_creates_a_rotating_qr_session(): void
    {
        $this->actingAsApi($this->lecturer)->postJson($this->api('/start'), [
            'section_id' => $this->section->id,
            'session_type' => 'lab',
            'week_number' => 4,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Attendance session started.')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.session_type', 'lab')
            ->assertJsonPath('data.week_number', 4)
            ->assertJsonPath('data.qr_mode', 'rotating')
            ->assertJsonCount(2, 'data.not_checked_in');

        $this->assertDatabaseHas('attendance_sessions', [
            'section_id' => $this->section->id,
            'lecturer_id' => $this->lecturer->id,
            'status' => 'active',
        ]);
    }

    public function test_start_conflicts_when_the_section_already_has_an_active_session(): void
    {
        $existing = $this->startAttendance($this->section, $this->lecturer);

        $this->actingAsApi($this->lecturer)->postJson($this->api('/start'), [
            'section_id' => $this->section->id,
            'session_type' => 'lecture',
        ])
            ->assertStatus(409)
            ->assertJsonPath('data.session_id', $existing->id);
    }

    public function test_start_validates_input(): void
    {
        $this->actingAsApi($this->lecturer)->postJson($this->api('/start'), ['session_type' => 'party'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['section_id', 'session_type']);
    }

    public function test_start_is_forbidden_for_an_unrelated_lecturer(): void
    {
        $outsider = $this->createMember($this->tenant, 'lecturer');

        $this->actingAsApi($outsider)->postJson($this->api('/start'), [
            'section_id' => $this->section->id,
            'session_type' => 'lecture',
        ])->assertForbidden();
    }

    public function test_start_with_a_section_from_another_tenant_is_not_found(): void
    {
        $otherTenant = $this->createTenant();
        $foreignSection = $this->createSection($this->createCourse($otherTenant, $this->createMember($otherTenant, 'lecturer')));

        $this->actingAsApi($this->lecturer)->postJson($this->api('/start'), [
            'section_id' => $foreignSection->id,
            'session_type' => 'lecture',
        ])->assertNotFound();
    }

    public function test_show_lists_records_counts_and_students_not_checked_in(): void
    {
        TenantUser::where('user_id', $this->aina->id)->update(['student_id_number' => 'A21EC0001']);
        $session = $this->startAttendance($this->section, $this->lecturer);
        $this->markAttendance($session, $this->aina);

        $this->actingAsApi($this->lecturer)->getJson($this->api("/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.counts.present', 1)
            ->assertJsonPath('data.total_students', 2)
            ->assertJsonCount(1, 'data.records')
            ->assertJsonPath('data.records.0.user.name', 'Aina Sofea')
            ->assertJsonPath('data.records.0.student_id_number', 'A21EC0001')
            ->assertJsonPath('data.records.0.method', 'qr_scan')
            ->assertJsonPath('data.not_checked_in.0.name', 'Zul Hakim');
    }

    public function test_token_returns_a_payload_students_can_check_in_with(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);
        $this->markAttendance($session, $this->aina);

        $response = $this->actingAsApi($this->lecturer)->getJson($this->api("/{$session->id}/token"))
            ->assertOk()
            ->assertJsonPath('data.rotation_seconds', 30)
            ->assertJsonPath('data.checked_in', 1)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.records.0.name', 'Aina Sofea');

        $qr = app(QrCodeService::class);
        $parsed = $qr->parsePayload($response->json('data.payload'));

        $this->assertSame($session->id, $parsed['session_id']);
        $this->assertTrue($qr->validateToken($parsed['token'], $session->qr_secret, 30));
        $this->assertGreaterThanOrEqual(1, $response->json('data.expires_in'));
        $this->assertLessThanOrEqual(30, $response->json('data.expires_in'));
    }

    public function test_token_for_an_ended_session_conflicts(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer, ['status' => 'ended', 'ended_at' => now()]);

        $this->actingAsApi($this->lecturer)->getJson($this->api("/{$session->id}/token"))
            ->assertStatus(409);
    }

    public function test_end_marks_remaining_students_absent(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);
        $this->markAttendance($session, $this->aina);

        $this->actingAsApi($this->lecturer)->postJson($this->api("/{$session->id}/end"))
            ->assertOk()
            ->assertJsonPath('message', 'Session ended. 1 students marked absent.')
            ->assertJsonPath('data.marked_absent', 1)
            ->assertJsonPath('data.session.status', 'ended')
            ->assertJsonPath('data.session.counts.absent', 1);

        $this->assertDatabaseHas('attendance_records', ['attendance_session_id' => $session->id, 'user_id' => $this->zul->id, 'status' => 'absent']);
        $this->assertDatabaseHas('attendance_sessions', ['id' => $session->id, 'status' => 'ended']);
    }

    public function test_ending_an_ended_session_conflicts(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer, ['status' => 'ended', 'ended_at' => now()]);

        $this->actingAsApi($this->lecturer)->postJson($this->api("/{$session->id}/end"))->assertStatus(409);
    }

    public function test_reopen_removes_only_automatic_absences(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer, ['status' => 'ended', 'ended_at' => now()]);
        $automatic = $this->markAttendance($session, $this->aina, 'absent');
        $overridden = $this->markAttendance($session, $this->zul, 'absent', ['override_by' => $this->lecturer->id, 'override_reason' => 'Confirmed']);

        $this->actingAsApi($this->lecturer)->postJson($this->api("/{$session->id}/reopen"))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseMissing('attendance_records', ['id' => $automatic->id]);
        $this->assertDatabaseHas('attendance_records', ['id' => $overridden->id]);
    }

    public function test_reopen_requires_an_ended_session(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);

        $this->actingAsApi($this->lecturer)->postJson($this->api("/{$session->id}/reopen"))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only ended sessions can be reopened.');
    }

    public function test_update_changes_type_and_week(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);

        $this->actingAsApi($this->lecturer)->putJson($this->api("/{$session->id}"), ['session_type' => 'tutorial', 'week_number' => 7])
            ->assertOk()
            ->assertJsonPath('data.session_type', 'tutorial')
            ->assertJsonPath('data.week_number', 7);

        $this->putJson($this->api("/{$session->id}"), ['session_type' => 'party'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('session_type');
    }

    public function test_override_updates_a_record(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);
        $record = $this->markAttendance($session, $this->zul, 'absent');

        $this->actingAsApi($this->lecturer)->putJson($this->api("/{$session->id}/records/{$record->id}"), [
            'status' => 'excused',
            'reason' => 'Medical leave',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'excused')
            ->assertJsonPath('data.override.by.id', $this->lecturer->id)
            ->assertJsonPath('data.override.reason', 'Medical leave');

        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'status' => 'excused', 'override_by' => $this->lecturer->id]);

        $this->putJson($this->api("/{$session->id}/records/{$record->id}"), ['status' => 'sleeping'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_override_rejects_a_record_from_another_session(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);
        $other = $this->startAttendance($this->section, $this->lecturer, ['status' => 'ended', 'ended_at' => now()]);
        $record = $this->markAttendance($other, $this->zul, 'absent');

        $this->actingAsApi($this->lecturer)->putJson($this->api("/{$session->id}/records/{$record->id}"), ['status' => 'present'])
            ->assertNotFound();

        $this->assertSame('absent', AttendanceRecord::find($record->id)->status);
    }

    public function test_destroy_refuses_an_active_session_then_deletes_an_ended_one(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);
        $this->markAttendance($session, $this->aina);

        $this->actingAsApi($this->lecturer)->deleteJson($this->api("/{$session->id}"))->assertStatus(409);

        $session->update(['status' => 'ended', 'ended_at' => now()]);

        $this->deleteJson($this->api("/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);

        $this->assertDatabaseMissing('attendance_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('attendance_records', ['attendance_session_id' => $session->id]);
    }

    public function test_section_lecturer_can_run_a_session_started_by_the_owner(): void
    {
        $colleague = $this->createMember($this->tenant, 'lecturer');
        $this->section->lecturers()->attach($colleague->id);
        $session = $this->startAttendance($this->section, $this->lecturer);

        $this->actingAsApi($colleague)->getJson($this->api("/{$session->id}/token"))->assertOk();
    }

    public function test_unrelated_lecturer_is_forbidden(): void
    {
        $session = $this->startAttendance($this->section, $this->lecturer);
        $outsider = $this->createMember($this->tenant, 'lecturer');

        $this->actingAsApi($outsider)->getJson($this->api("/{$session->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have access to this attendance session.');
    }

    public function test_session_from_another_tenant_is_not_found(): void
    {
        $otherTenant = $this->createTenant();
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');
        $foreign = $this->startAttendance($this->createSection($this->createCourse($otherTenant, $otherLecturer)), $otherLecturer);

        $this->actingAsApi($this->lecturer)->getJson($this->api("/{$foreign->id}"))->assertNotFound();
    }

    public function test_students_cannot_use_the_lecturer_attendance_api(): void
    {
        $this->actingAsApi($this->aina)->getJson($this->api())
            ->assertForbidden()
            ->assertJsonPath('message', 'This area is for lecturers.');
    }
}
