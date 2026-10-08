<?php

namespace Tests\Feature\Tenant;

use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The course overview's headline figures. Extends the API test case for its
 * tenant fixtures; the route under test is a web route.
 */
class CourseOverviewTest extends ApiTestCase
{
    public function test_the_students_stat_leaves_out_deleted_accounts_like_the_section_page_does(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course);
        $this->enroll($section, $this->createMember($tenant, 'student'));
        $gone = $this->createMember($tenant, 'student');
        $this->enroll($section, $gone);
        $gone->delete();

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}")
            ->assertOk();

        $this->assertSame(1, $section->activeStudents()->count());
        $this->assertSame(1, $course->totalStudents());
    }
}
