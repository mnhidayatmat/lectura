<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\Lecturer\LecturerApiTestCase;

/**
 * The attendance report covers the viewer's own sections only: a co-lecturer's section
 * (its students, its sessions) never appears, and asking for it by id is a 404.
 */
class AttendanceReportSectionsTest extends LecturerApiTestCase
{
    /**
     * @return array{0: Tenant, 1: Course, 2: User, 3: User, 4: Section, 5: Section}
     */
    private function sharedCourse(): array
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer', ['name' => 'Owner Lecturer']);
        $colleague = $this->createMember($tenant, 'lecturer', ['name' => 'Colleague Lecturer']);
        $course = $this->createCourse($tenant, $owner);

        $mine = $this->createSection($course, ['name' => 'Section 01'], [$owner]);
        $theirs = $this->createSection($course, ['name' => 'Section 02', 'code' => '02'], [$colleague]);
        $this->enroll($mine, $this->createMember($tenant, 'student', ['name' => 'My Student']));
        $this->enroll($theirs, $this->createMember($tenant, 'student', ['name' => 'Their Student']));
        $this->startAttendance($mine, $owner, ['status' => 'ended', 'ended_at' => now()]);
        $this->startAttendance($theirs, $colleague, ['status' => 'ended', 'ended_at' => now()]);

        return [$tenant, $course, $owner, $colleague, $mine, $theirs];
    }

    public function test_each_lecturer_sees_only_their_own_sections(): void
    {
        [$tenant, $course, $owner, $colleague, $mine, $theirs] = $this->sharedCourse();
        $url = "/{$tenant->slug}/attendance/report/{$course->id}";

        $this->actingAs($colleague)->get($url)->assertOk()
            ->assertSee('Their Student')
            ->assertDontSee('My Student')
            ->assertViewHas('report', fn (array $report) => $report['sessions']->count() === 1)
            ->assertViewHas('sections', fn ($sections) => $sections->pluck('id')->all() === [$theirs->id]);

        $this->actingAs($owner)->get($url)->assertOk()
            ->assertSee('My Student')
            ->assertDontSee('Their Student');
    }

    public function test_another_lecturers_section_cannot_be_opened_or_exported(): void
    {
        [$tenant, $course, , $colleague, $mine] = $this->sharedCourse();
        $url = "/{$tenant->slug}/attendance/report/{$course->id}";

        foreach (['', '/pdf', '/excel'] as $suffix) {
            $this->actingAs($colleague)->get($url.$suffix.'?section_id='.$mine->id)->assertNotFound();
        }
    }
}
