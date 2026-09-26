<?php

namespace Tests\Feature\Tenant;

use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Netflix-style course context: selecting a course stores it in the session,
 * flat feature indexes redirect into it, access rules match the course pages,
 * and students are unaffected.
 */
class CourseContextTest extends ApiTestCase
{
    public function test_select_stores_context_and_lands_on_course_hub(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", [
                'course_id' => $course->id,
                'redirect' => "/{$tenant->slug}/courses/{$course->id}",
            ])
            ->assertRedirect("/{$tenant->slug}/courses/{$course->id}");

        // Context is active: the flat attendance index now redirects into the course.
        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/attendance")
            ->assertRedirect("/{$tenant->slug}/attendance/course/{$course->id}");
    }

    public function test_select_without_redirect_falls_back_to_course_hub(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $course->id])
            ->assertRedirect("/{$tenant->slug}/courses/{$course->id}");
    }

    public function test_select_rejects_an_external_redirect(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", [
                'course_id' => $course->id,
                'redirect' => 'https://evil.example.com',
            ])
            ->assertRedirect("/{$tenant->slug}/courses/{$course->id}");
    }

    public function test_selecting_an_inaccessible_course_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $otherLecturer = $this->createMember($tenant, 'lecturer');
        $foreignCourse = $this->createCourse($tenant, $otherLecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $foreignCourse->id])
            ->assertForbidden();
    }

    public function test_selecting_a_cross_tenant_course_is_not_found(): void
    {
        $tenant = $this->createTenant();
        $otherTenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');
        $foreignCourse = $this->createCourse($otherTenant, $otherLecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $foreignCourse->id])
            ->assertNotFound();
    }

    public function test_clear_resets_context(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        // A second course so auto-select does not re-pick after clearing.
        $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $course->id]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/attendance")
            ->assertRedirect("/{$tenant->slug}/attendance/course/{$course->id}");

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context/clear")
            ->assertRedirect("/{$tenant->slug}/courses");

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/attendance")
            ->assertOk();
    }

    public function test_single_course_lecturer_is_auto_selected(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/attendance")
            ->assertRedirect("/{$tenant->slug}/attendance/course/{$course->id}");
    }

    public function test_student_role_is_unaffected(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAs($student)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk();

        // No context is bound for students: the flat index renders as before.
        $this->assertNull(app()->bound('current_course') ? app('current_course') : null);
    }
}
