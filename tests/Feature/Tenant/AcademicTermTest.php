<?php

namespace Tests\Feature\Tenant;

use App\Models\AcademicTerm;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Closing and reopening a semester. Extends the API test case because its
 * fixtures are the only tenant fixtures in the suite; the routes under test are
 * ordinary web routes.
 */
class AcademicTermTest extends ApiTestCase
{
    public function test_closing_a_semester_marks_it_closed_and_leaves_its_courses_alone(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createMember($tenant, 'admin');
        $lecturer = $this->createMember($tenant, 'lecturer');
        $term = $this->createTerm($tenant);
        $course = $this->createCourse($tenant, $lecturer);
        $this->createSection($course, ['academic_term_id' => $term->id]);

        $this->actingAs($admin)
            ->post("/{$tenant->slug}/semesters/{$term->id}/close")
            ->assertRedirect("/{$tenant->slug}/semesters")
            ->assertSessionHas('success');

        $this->assertTrue($term->fresh()->isClosed());
        // A course carries on into other semesters, so closing one never archives it.
        $this->assertSame('active', $course->fresh()->status);
    }

    public function test_reopening_a_closed_semester(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createMember($tenant, 'admin');
        $term = $this->createTerm($tenant, ['closed_at' => now()]);

        $this->actingAs($admin)
            ->post("/{$tenant->slug}/semesters/{$term->id}/reopen")
            ->assertRedirect("/{$tenant->slug}/semesters");

        $this->assertFalse($term->fresh()->isClosed());
    }

    public function test_closing_an_already_closed_semester_reports_instead_of_pretending(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createMember($tenant, 'admin');
        $term = $this->createTerm($tenant, ['closed_at' => now()->subDay()]);

        $this->actingAs($admin)
            ->post("/{$tenant->slug}/semesters/{$term->id}/close")
            ->assertRedirect("/{$tenant->slug}/semesters")
            ->assertSessionHas('error');
    }

    public function test_a_lecturer_cannot_close_a_semester(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $term = $this->createTerm($tenant);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/semesters/{$term->id}/close")
            ->assertForbidden();

        $this->assertFalse($term->fresh()->isClosed());
    }

    public function test_a_semester_from_another_institution_is_not_reachable(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createMember($tenant, 'admin');

        $other = $this->createTenant();
        $foreignTerm = $this->createTerm($other);

        $this->actingAs($admin)
            ->post("/{$tenant->slug}/semesters/{$foreignTerm->id}/close")
            ->assertNotFound();

        $this->assertFalse(AcademicTerm::withoutGlobalScopes()->find($foreignTerm->id)->isClosed());
    }

    /**
     * Regression: an admin really could delete another institution's semester.
     * Web binding resolves the model before the tenant is bound, so the global
     * scope no-ops, and destroy()'s "has courses?" guard then counts only the
     * caller's own courses — finds none, and deletes.
     */
    public function test_a_semester_from_another_institution_cannot_be_deleted(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createMember($tenant, 'admin');

        $other = $this->createTenant();
        $foreignTerm = $this->createTerm($other);
        $this->createCourse($other, $this->createMember($other, 'lecturer'), [
            'academic_term_id' => $foreignTerm->id,
        ]);

        $this->actingAs($admin)
            ->delete("/{$tenant->slug}/semesters/{$foreignTerm->id}")
            ->assertNotFound();

        // withoutGlobalScopes, or the assertion passes by being unable to see it.
        $this->assertNotNull(AcademicTerm::withoutGlobalScopes()->find($foreignTerm->id));
    }
}
