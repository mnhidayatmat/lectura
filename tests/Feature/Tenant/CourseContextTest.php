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

    public function test_select_without_redirect_lands_on_course_home(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $course->id])
            ->assertRedirect("/{$tenant->slug}/dashboard");
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
            ->assertRedirect("/{$tenant->slug}/dashboard");
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
            ->assertRedirect("/{$tenant->slug}/choose-course");

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

    public function test_dashboard_sends_multi_course_lecturer_to_the_picker(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $first = $this->createCourse($tenant, $lecturer, ['code' => 'BTD2232']);
        $this->createCourse($tenant, $lecturer, ['code' => 'BTG2663']);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/dashboard")
            ->assertRedirect("/{$tenant->slug}/choose-course");

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/choose-course")
            ->assertOk()
            ->assertSee('BTD2232')
            ->assertSee('BTG2663');

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $first->id])
            ->assertRedirect("/{$tenant->slug}/dashboard");

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertViewIs('tenant.course-home')
            ->assertSee($first->title);
    }

    public function test_lecturer_without_courses_gets_the_plain_dashboard(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/dashboard")
            ->assertOk()
            ->assertViewIs('tenant.dashboard');
    }

    public function test_opening_a_course_page_makes_it_the_context(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $first = $this->createCourse($tenant, $lecturer);
        $second = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $first->id]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$second->id}")
            ->assertOk();

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/attendance")
            ->assertRedirect("/{$tenant->slug}/attendance/course/{$second->id}");
    }

    public function test_opening_someone_elses_course_does_not_change_the_context(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $own = $this->createCourse($tenant, $lecturer);
        $this->createCourse($tenant, $lecturer);
        $foreign = $this->createCourse($tenant, $this->createMember($tenant, 'lecturer'));

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/course-context", ['course_id' => $own->id]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$foreign->id}")
            ->assertForbidden();

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/attendance")
            ->assertRedirect("/{$tenant->slug}/attendance/course/{$own->id}");
    }

    public function test_switcher_keeps_the_lecturer_on_the_same_tool(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $first = $this->createCourse($tenant, $lecturer);
        $second = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/attendance/course/{$first->id}")
            ->assertOk()
            ->assertSee('value="/'.$tenant->slug.'/attendance/course/'.$second->id.'"', false);
    }
}
