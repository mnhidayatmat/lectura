<?php

namespace Tests\Feature\Tenant;

use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Joining a section from My Courses. Extends the API test case for its tenant
 * fixtures; the routes under test are web routes.
 */
class StudentEnrollTest extends ApiTestCase
{
    public function test_joining_a_section_again_tells_the_student_they_are_already_in_it(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $student = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']);
        $section = $this->createSection($course, ['invite_code' => 'BTG3333S1']);
        $this->enroll($section, $student);
        $myCourses = "/{$tenant->slug}/my-courses";

        $this->actingAs($student)
            ->from($myCourses)
            ->post("{$myCourses}/enroll", ['invite_code' => 'btg3333s1'])
            ->assertRedirect($myCourses)
            ->assertSessionHas('info');

        // The flash is an `info`, which the layout used to drop on the floor.
        $this->actingAs($student)
            ->get($myCourses)
            ->assertOk()
            ->assertSee('You are already enrolled in BTG3333');
    }
}
