<?php

namespace Tests\Feature\Tenant;

use Illuminate\Support\Str;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The lecturer course list shows each course once, with the semesters it runs
 * in, and folds archived courses away. Extends the API test case for its tenant
 * fixtures; the route is a web route.
 */
class CourseIndexTest extends ApiTestCase
{
    public function test_each_course_is_listed_once_with_the_semesters_it_runs_in(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $older = $this->createTerm($tenant, [
            'name' => 'Semester 1, 2025/2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-28',
        ]);
        $newer = $this->createTerm($tenant, [
            'name' => 'Semester 2, 2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
        ]);

        $piping = $this->createCourse($tenant, $lecturer, ['code' => 'SKMM2222']);
        $this->createSection($piping, ['academic_term_id' => $older->id]);
        $this->createSection($piping, ['name' => 'Section 02', 'code' => '02', 'academic_term_id' => $newer->id]);
        $this->createCourse($tenant, $lecturer, ['code' => 'SKMM1111']);

        $response = $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses")
            ->assertOk()
            ->assertSeeInOrder(['SKMM1111', 'SKMM2222', 'Semester 2, 2026/2027', 'Semester 1, 2025/2026']);

        // Counted inside <main>: the layout's course switcher lists every course too.
        $this->assertSame(1, substr_count(Str::after($response->getContent(), '<main'), '>SKMM2222<'));
    }

    public function test_archived_courses_are_folded_into_their_own_list(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $this->createCourse($tenant, $lecturer, ['code' => 'SKMMOPEN']);
        $this->createCourse($tenant, $lecturer, ['code' => 'SKMMOLD', 'status' => 'archived']);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses")
            ->assertOk()
            ->assertSeeInOrder(['SKMMOPEN', 'Archived courses (1)', 'SKMMOLD']);
    }

    public function test_a_lecturer_with_no_courses_still_sees_the_empty_state(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses")
            ->assertOk()
            ->assertSee('No courses yet')
            ->assertDontSee('Archived courses');
    }
}
