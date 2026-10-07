<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\AttendanceExcuse;
use App\Models\AttendanceRecord;
use App\Models\Tenant;
use App\Models\User;

class ExcuseApiTest extends LecturerApiTestCase
{
    /**
     * @return array{0: Tenant, 1: User, 2: AttendanceExcuse}
     */
    private function pendingExcuse(): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $student = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'SKM1001']);
        $section = $this->createSection($course);
        $this->enroll($section, $student);
        $session = $this->startAttendance($section, $lecturer, ['status' => 'ended', 'ended_at' => now()]);
        $record = $this->markAttendance($session, $student, 'absent');

        $excuse = AttendanceExcuse::create([
            'attendance_record_id' => $record->id,
            'user_id' => $student->id,
            'reason' => 'Hospital appointment',
            'category' => 'medical',
            'status' => 'pending',
        ]);

        return [$tenant, $lecturer, $excuse];
    }

    public function test_index_lists_pending_excuses_for_the_lecturers_courses(): void
    {
        [$tenant, $lecturer, $excuse] = $this->pendingExcuse();
        $stranger = $this->createMember($tenant, 'lecturer');

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/excuses'))
            ->assertOk()
            ->assertJsonPath('meta.pending_count', 1)
            ->assertJsonPath('data.0.id', $excuse->id)
            ->assertJsonPath('data.0.course.code', 'SKM1001')
            ->assertJsonPath('data.0.category_label', 'Medical')
            ->assertJsonPath('data.0.has_attachment', false);

        $this->actingAsApi($stranger)->getJson($this->tenantApi($tenant, 'lecturer/excuses'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAsApi($lecturer)->getJson($this->tenantApi($tenant, 'lecturer/excuses?status=approved'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_approving_marks_the_record_excused_and_cannot_be_repeated(): void
    {
        [$tenant, $lecturer, $excuse] = $this->pendingExcuse();

        $this->actingAsApi($lecturer)
            ->postJson($this->tenantApi($tenant, "lecturer/excuses/{$excuse->id}/approve"), ['note' => 'Get well soon'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.record.status', 'excused')
            ->assertJsonPath('data.reviewer_note', 'Get well soon')
            ->assertJsonPath('data.reviewer.id', $lecturer->id);

        $this->assertSame('excused', AttendanceRecord::find($excuse->attendance_record_id)->status);

        $this->actingAsApi($lecturer)
            ->postJson($this->tenantApi($tenant, "lecturer/excuses/{$excuse->id}/reject"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This excuse has already been approved.');
    }

    public function test_rejecting_leaves_the_record_absent(): void
    {
        [$tenant, $lecturer, $excuse] = $this->pendingExcuse();

        $this->actingAsApi($lecturer)
            ->postJson($this->tenantApi($tenant, "lecturer/excuses/{$excuse->id}/reject"))
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.record.status', 'absent');
    }

    public function test_other_lecturers_and_other_institutions_cannot_review(): void
    {
        [$tenant, , $excuse] = $this->pendingExcuse();
        $stranger = $this->createMember($tenant, 'lecturer');

        $this->actingAsApi($stranger)
            ->postJson($this->tenantApi($tenant, "lecturer/excuses/{$excuse->id}/approve"))
            ->assertForbidden();

        // An admin elsewhere must not reach this tenant's excuse through their own tenant.
        $otherTenant = $this->createTenant();
        $otherAdmin = $this->createMember($otherTenant, 'admin');

        $this->actingAsApi($otherAdmin)
            ->postJson($this->tenantApi($otherTenant, "lecturer/excuses/{$excuse->id}/approve"))
            ->assertNotFound();

        $this->assertSame('pending', $excuse->fresh()->status);
    }

    public function test_attachment_is_404_when_there_is_none(): void
    {
        [$tenant, $lecturer, $excuse] = $this->pendingExcuse();

        $this->actingAsApi($lecturer)
            ->getJson($this->tenantApi($tenant, "lecturer/excuses/{$excuse->id}/attachment"))
            ->assertNotFound();
    }
}
