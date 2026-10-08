<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The Quick Links card on /profile. The page sits outside the tenant prefix, so
 * it has to find the user's institution itself. Extends the API test case for
 * its tenant fixtures.
 */
class ProfileQuickLinksTest extends ApiTestCase
{
    public function test_a_student_gets_their_own_pages(): void
    {
        $tenant = $this->createTenant();
        $student = $this->createMember($tenant, 'student');

        $this->actingAs($student)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Quick Links')
            ->assertSee("/{$tenant->slug}/my-courses")
            ->assertSee("/{$tenant->slug}/my-attendance")
            ->assertSee("/{$tenant->slug}/marks")
            ->assertSee("/{$tenant->slug}/watch")
            ->assertDontSee("/{$tenant->slug}/materials")
            ->assertSee('window.livewireScriptConfig =', false);
    }

    public function test_a_lecturer_gets_the_teaching_pages(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');

        $this->actingAs($lecturer)
            ->get('/profile')
            ->assertOk()
            ->assertSee("/{$tenant->slug}/courses")
            ->assertSee("/{$tenant->slug}/attendance")
            ->assertSee("/{$tenant->slug}/materials")
            ->assertSee("/{$tenant->slug}/settings")
            ->assertDontSee("/{$tenant->slug}/my-courses");
    }

    public function test_the_card_is_hidden_without_an_institution(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/profile')
            ->assertOk()
            ->assertDontSee('Quick Links');
    }
}
