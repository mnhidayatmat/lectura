<?php

namespace Tests\Feature\Tenant;

use App\Models\TenderGame;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * A past semester's sections are switched off: the tender game offers only the
 * active ones, and an "All sections" game takes its roster from them alone.
 */
class TenderActiveSectionsTest extends ApiTestCase
{
    public function test_only_active_sections_are_offered_and_rostered(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer, ['code' => 'BTG2663']);
        $old = $this->createSection($course, ['name' => 'Last semester', 'is_active' => false], [$lecturer]);
        $current = $this->createSection($course, ['name' => 'This semester'], [$lecturer]);
        $former = $this->createMember($tenant, 'student', ['name' => 'Former Student']);
        $now = $this->createMember($tenant, 'student', ['name' => 'Current Student']);
        $this->enroll($old, $former);
        $this->enroll($current, $now);
        $base = "/{$tenant->slug}/courses/{$course->id}/tender";

        $this->actingAs($lecturer)->get($base)
            ->assertOk()
            ->assertSee('This semester')
            ->assertDontSee('Last semester');

        $this->post($base, [
            'title' => 'Old section', 'section_id' => $old->id,
            'company_a_name' => 'Alpha', 'company_b_name' => 'Beta',
        ])->assertSessionHasErrors('section_id');

        $this->post($base, [
            'title' => 'Everyone', 'section_id' => '',
            'company_a_name' => 'Alpha', 'company_b_name' => 'Beta',
        ])->assertRedirect();
        $game = TenderGame::withoutGlobalScopes()->latest('id')->first();

        $this->get("{$base}/{$game->id}")
            ->assertOk()
            ->assertSee('Current Student')
            ->assertDontSee('Former Student');
    }

    public function test_a_game_on_an_old_section_can_still_be_edited(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $old = $this->createSection($course, ['name' => 'Last semester', 'is_active' => false], [$lecturer]);
        $game = TenderGame::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'course_id' => $course->id, 'section_id' => $old->id,
            'title' => 'Project TERATAI', 'company_a_name' => 'Alpha', 'company_b_name' => 'Beta',
        ]);
        $base = "/{$tenant->slug}/courses/{$course->id}/tender/{$game->id}";

        $this->actingAs($lecturer)->get($base)->assertOk()->assertSee('Last semester');
        $this->put($base, [
            'title' => 'Renamed', 'section_id' => $old->id,
            'company_a_name' => 'Alpha', 'company_b_name' => 'Beta',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $game->fresh()->title);
    }
}
