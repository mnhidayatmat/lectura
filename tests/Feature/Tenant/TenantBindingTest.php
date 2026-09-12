<?php

namespace Tests\Feature\Tenant;

use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Pins the web middleware ordering: ResolveTenant runs before SubstituteBindings
 * (bootstrap/app.php), so BelongsToTenant's global scope is active while models
 * are bound from the URL.
 *
 * The app has ~183 model-bound web tenant routes and almost no route-level
 * coverage, so this checks both directions on a representative one outside the
 * semester feature: your own record must still bind, another institution's must
 * not. The first test is the regression guard — if the ordering is ever wrong in
 * the other direction, legitimate pages start 404ing.
 */
class TenantBindingTest extends ApiTestCase
{
    public function test_a_lecturer_can_still_open_their_own_course(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}")
            ->assertOk();
    }

    public function test_another_institutions_course_does_not_bind(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $other = $this->createTenant();
        $foreign = $this->createCourse($other, $this->createMember($other, 'lecturer'));

        // Before the ordering fix this bound while current_tenant was unset, so the
        // tenant scope no-opped and another institution's course reached the
        // controller — the same flaw that let a foreign semester be deleted.
        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$foreign->id}")
            ->assertNotFound();
    }
}
