<?php

namespace Tests\Feature\Tenant;

use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The lecturer course list groups by semester and folds archived courses away.
 * Extends the API test case for its tenant fixtures; the route is a web route.
 */
class CourseIndexTest extends ApiTestCase
{
    public function test_courses_are_grouped_by_semester_with_the_newest_first(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        // Named so insertion order and alphabetical order disagree, or an
        // accidental sort by name would satisfy this assertion too.
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

        $this->createCourse($tenant, $lecturer, ['code' => 'SKMM1111', 'academic_term_id' => $older->id]);
        $this->createCourse($tenant, $lecturer, ['code' => 'SKMM2222', 'academic_term_id' => $newer->id]);
        $this->createCourse($tenant, $lecturer, ['code' => 'SKMM3333']);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses")
            ->assertOk()
            ->assertSeeInOrder(['Semester 2, 2026/2027', 'SKMM2222', 'Semester 1, 2025/2026', 'SKMM1111', 'No semester', 'SKMM3333']);
    }

    public function test_archived_courses_move_out_of_the_semesters_into_their_own_section(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $term = $this->createTerm($tenant, ['name' => 'Semester 1, 2026/2027']);

        $this->createCourse($tenant, $lecturer, ['code' => 'SKMMOPEN', 'academic_term_id' => $term->id]);
        $this->createCourse($tenant, $lecturer, [
            'code' => 'SKMMOLD',
            'academic_term_id' => $term->id,
            'status' => 'archived',
        ]);

        $response = $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses")
            ->assertOk()
            ->assertSee('Archived courses (1)');

        // The open course is counted under its semester; the archived one is not.
        $response->assertSeeInOrder(['Semester 1, 2026/2027', '1 course', 'SKMMOPEN', 'Archived courses (1)', 'SKMMOLD']);
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
