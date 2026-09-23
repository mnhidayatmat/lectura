<?php

namespace Tests\Feature\Tenant;

use App\Models\AcademicTerm;
use App\Models\AttendanceExcuse;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\Lecturer\LecturerApiTestCase;

/**
 * Past attendance is locked once its semester is closed (course archived) or once
 * the session ended more than `lectura.attendance.lock_after_days` ago.
 */
class AttendanceLockTest extends LecturerApiTestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $lecturer;

    private AcademicTerm $term;

    private Course $course;

    private Section $section;

    private User $aina;

    private User $zul;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->admin = $this->createMember($this->tenant, 'admin');
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->term = $this->createTerm($this->tenant);
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['academic_term_id' => $this->term->id]);
        $this->section = $this->createSection($this->course);
        $this->aina = $this->createMember($this->tenant, 'student', ['name' => 'Aina Sofea']);
        $this->zul = $this->createMember($this->tenant, 'student', ['name' => 'Zul Hakim']);
        $this->enroll($this->section, $this->aina);
        $this->enroll($this->section, $this->zul);
    }

    private function web(string $path): string
    {
        return "/{$this->tenant->slug}{$path}";
    }

    private function api(string $path): string
    {
        return $this->tenantApi($this->tenant, $path);
    }

    private function closeSemester(): void
    {
        $this->actingAs($this->admin)
            ->post($this->web("/semesters/{$this->term->id}/archive-courses"))
            ->assertRedirect($this->web('/semesters'));
    }

    private function endedSession(int $daysAgo = 1): AttendanceSession
    {
        return $this->startAttendance($this->section, $this->lecturer, [
            'status' => 'ended',
            'started_at' => now()->subDays($daysAgo)->subHour(),
            'ended_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_closing_a_semester_ends_running_sessions_and_marks_no_shows_absent(): void
    {
        $running = $this->startAttendance($this->section, $this->lecturer);
        $this->markAttendance($running, $this->aina);

        $this->closeSemester();

        $running->refresh();
        $this->assertSame('ended', $running->status);
        $this->assertNotNull($running->ended_at);
        $this->assertSame('absent', $running->records()->where('user_id', $this->zul->id)->value('status'));
        $this->assertSame('semester_closed', $running->lockReason());
    }

    public function test_a_closed_semester_blocks_every_change_on_the_web(): void
    {
        $session = $this->endedSession();
        $record = $this->markAttendance($session, $this->zul, 'absent');

        $this->closeSemester();

        $this->actingAs($this->lecturer)
            ->put($this->web("/attendance/{$session->id}/records/{$record->id}"), ['status' => 'present'])
            ->assertSessionHas('error');
        $this->assertSame('absent', $record->fresh()->status);

        $this->actingAs($this->lecturer)
            ->post($this->web("/attendance/{$session->id}/reopen"))
            ->assertSessionHas('error');
        $this->assertSame('ended', $session->fresh()->status);

        $this->actingAs($this->lecturer)
            ->put($this->web("/attendance/{$session->id}"), ['session_type' => 'lab'])
            ->assertSessionHas('error');
        $this->assertSame('lecture', $session->fresh()->session_type);

        $this->actingAs($this->lecturer)
            ->delete($this->web("/attendance/{$session->id}"))
            ->assertSessionHas('error');
        $this->assertNotNull($session->fresh());

        $this->actingAs($this->lecturer)
            ->post($this->web('/attendance/start'), ['section_id' => $this->section->id, 'session_type' => 'lecture'])
            ->assertSessionHas('error');
        $this->assertSame(1, AttendanceSession::where('section_id', $this->section->id)->count());
    }

    public function test_reopening_the_semester_unlocks_its_attendance(): void
    {
        $session = $this->endedSession();
        $record = $this->markAttendance($session, $this->zul, 'absent');

        $this->closeSemester();
        $this->actingAs($this->admin)->post($this->web("/semesters/{$this->term->id}/reopen-courses"));

        $this->actingAs($this->lecturer)
            ->put($this->web("/attendance/{$session->id}/records/{$record->id}"), ['status' => 'present'])
            ->assertSessionHas('success');

        $this->assertSame('present', $record->fresh()->status);
    }

    public function test_the_web_override_checks_session_access_and_record_ownership(): void
    {
        $session = $this->endedSession();
        $record = $this->markAttendance($session, $this->zul, 'absent');

        $outsider = $this->createMember($this->tenant, 'lecturer');

        $this->actingAs($outsider)
            ->put($this->web("/attendance/{$session->id}/records/{$record->id}"), ['status' => 'present'])
            ->assertForbidden();

        $other = $this->endedSession();

        $this->actingAs($this->lecturer)
            ->put($this->web("/attendance/{$other->id}/records/{$record->id}"), ['status' => 'present'])
            ->assertNotFound();

        $this->assertSame('absent', $record->fresh()->status);
    }

    public function test_a_session_locks_once_its_edit_window_has_passed(): void
    {
        config(['lectura.attendance.lock_after_days' => 14]);

        $recent = $this->endedSession(13);
        $old = $this->endedSession(15);
        $record = $this->markAttendance($old, $this->zul, 'absent');

        $this->assertFalse($recent->isLocked());
        $this->assertSame('edit_window_passed', $old->lockReason());

        $this->actingAsApi($this->lecturer)
            ->putJson($this->api("lecturer/attendance/{$old->id}/records/{$record->id}"), ['status' => 'present'])
            ->assertStatus(409)
            ->assertJsonPath('data.lock_reason', 'edit_window_passed');

        $this->actingAsApi($this->lecturer)
            ->getJson($this->api("lecturer/attendance/{$old->id}"))
            ->assertOk()
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.lock_reason', 'edit_window_passed');

        config(['lectura.attendance.lock_after_days' => 0]);
        $this->assertFalse($old->fresh()->isLocked());
    }

    public function test_the_api_refuses_to_start_a_session_in_an_archived_course(): void
    {
        $this->course->update(['status' => 'archived']);

        $this->actingAsApi($this->lecturer)
            ->postJson($this->api('lecturer/attendance/start'), [
                'section_id' => $this->section->id,
                'session_type' => 'lecture',
            ])
            ->assertStatus(409);
    }

    public function test_students_cannot_submit_excuses_for_a_locked_session(): void
    {
        $session = $this->endedSession();
        $record = $this->markAttendance($session, $this->zul, 'absent');

        $this->closeSemester();

        $this->actingAsApi($this->zul)
            ->getJson($this->api("student/attendance/courses/{$this->course->id}"))
            ->assertOk()
            ->assertJsonPath('data.sessions.0.record.can_submit_excuse', false);

        $this->actingAsApi($this->zul)
            ->postJson($this->api("student/attendance/records/{$record->id}/excuse"), [
                'reason' => 'Fever',
                'category' => 'medical',
            ])
            ->assertStatus(422);

        $this->assertNull($record->fresh()->excuse);
    }

    public function test_pending_excuses_stay_reviewable_past_the_edit_window_but_not_after_close(): void
    {
        config(['lectura.attendance.lock_after_days' => 14]);

        $session = $this->endedSession(20);
        $record = $this->markAttendance($session, $this->zul, 'absent');
        $excuse = AttendanceExcuse::create([
            'attendance_record_id' => $record->id,
            'user_id' => $this->zul->id,
            'reason' => 'Fever',
            'category' => 'medical',
            'status' => 'pending',
        ]);

        $this->actingAs($this->lecturer)
            ->put($this->web("/attendance/excuses/{$excuse->id}/approve"))
            ->assertSessionHas('success');
        $this->assertSame('excused', $record->fresh()->status);

        $excuse->fresh()->update(['status' => 'pending']);
        $this->closeSemester();

        $this->actingAs($this->lecturer)
            ->put($this->web("/attendance/excuses/{$excuse->id}/reject"))
            ->assertSessionHas('error');
        $this->assertSame('pending', $excuse->fresh()->status);
    }
}
