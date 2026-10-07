<?php

namespace Tests\Feature\Tenant;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\Lecturer\LecturerApiTestCase;

/**
 * A deactivated section drops out of the web attendance pages: its sessions,
 * history filter, reports and the start form, and no new session can start on it.
 */
class AttendanceInactiveSectionTest extends LecturerApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    private Section $active;

    private Section $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer);
        $this->active = $this->createSection($this->course, ['name' => 'Section Alpha']);
        $this->inactive = $this->createSection($this->course, ['name' => 'Section Omega', 'is_active' => false]);
    }

    private function web(string $path): string
    {
        return "/{$this->tenant->slug}{$path}";
    }

    public function test_course_attendance_page_hides_an_inactive_section_and_its_sessions(): void
    {
        $this->startAttendance($this->active, $this->lecturer, ['status' => 'ended', 'ended_at' => now()]);
        $this->startAttendance($this->inactive, $this->lecturer, ['status' => 'ended', 'ended_at' => now()]);
        $this->startAttendance($this->inactive, $this->lecturer);

        $this->actingAs($this->lecturer)
            ->get($this->web("/attendance/course/{$this->course->id}"))
            ->assertOk()
            ->assertSee('Section Alpha')
            ->assertDontSee('Section Omega')
            ->assertDontSee('Live now');
    }

    public function test_a_session_cannot_be_started_on_an_inactive_section(): void
    {
        $this->actingAs($this->lecturer)
            ->from($this->web("/attendance/course/{$this->course->id}"))
            ->post($this->web('/attendance/start'), [
                'section_id' => $this->inactive->id,
                'session_type' => 'lecture',
            ])
            ->assertRedirect($this->web("/attendance/course/{$this->course->id}"))
            ->assertSessionHas('error');

        $this->assertSame(0, AttendanceSession::where('section_id', $this->inactive->id)->count());
    }
}
