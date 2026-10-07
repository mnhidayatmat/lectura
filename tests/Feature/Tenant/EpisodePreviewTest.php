<?php

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * "Preview as student" on the lecturer's web Episodes page.
 */
class EpisodePreviewTest extends ApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3333']);
    }

    private function episode(array $attributes = []): Episode
    {
        $series = CourseSeries::firstOrCreate(
            ['course_id' => $this->course->id],
            ['tenant_id' => $this->tenant->id, 'title' => "Titis's Slime Journal"],
        );

        return Episode::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'course_series_id' => $series->id,
            'course_id' => $this->course->id,
            'episode_number' => 1,
            'title' => 'I Woke Up as an Oil Slime',
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => 'episodes/ep1.mp4',
            'duration_seconds' => 200,
        ], $attributes));
    }

    private function base(string $path = ''): string
    {
        return "/{$this->tenant->slug}/materials/course/{$this->course->id}/episodes{$path}";
    }

    public function test_episodes_page_links_to_the_student_preview(): void
    {
        $this->episode();

        $this->actingAs($this->lecturer)->get($this->base())
            ->assertOk()
            ->assertSee('Preview as student')
            ->assertSee($this->base('/preview'), false);
    }

    public function test_preview_shows_the_student_series_with_locked_episodes_playable(): void
    {
        $this->episode();
        $locked = $this->episode(['episode_number' => 2, 'title' => 'Plot, Plan and Isometric', 'status' => Episode::STATUS_LOCKED]);
        $this->episode(['episode_number' => 3, 'title' => 'A draft nobody sees', 'status' => Episode::STATUS_DRAFT]);

        $this->actingAs($this->lecturer)->get($this->base('/preview'))
            ->assertOk()
            ->assertSee('Student preview')
            ->assertSee('Plot, Plan and Isometric')
            ->assertSee('Coming soon')
            ->assertDontSee('A draft nobody sees')
            ->assertSee($this->base("/{$locked->id}/preview"), false);
    }

    public function test_episode_preview_plays_without_saving(): void
    {
        $locked = $this->episode(['status' => Episode::STATUS_LOCKED]);

        $this->actingAs($this->lecturer)->get($this->base("/{$locked->id}/preview"))
            ->assertOk()
            ->assertSee('Student preview')
            ->assertSee("aren't saved", false)
            ->assertDontSee("/watch/episodes/{$locked->id}/progress", false);
    }

    public function test_nothing_to_preview_goes_back_with_a_note(): void
    {
        $this->episode(['status' => Episode::STATUS_DRAFT]);

        $this->actingAs($this->lecturer)->get($this->base('/preview'))
            ->assertRedirect($this->base())
            ->assertSessionHas('error');
    }

    public function test_other_lecturers_and_other_courses_are_refused(): void
    {
        $episode = $this->episode();
        $stranger = $this->createMember($this->tenant, 'lecturer');
        $otherCourse = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3313']);

        $this->actingAs($stranger)->get($this->base('/preview'))->assertForbidden();
        $this->actingAs($this->lecturer)->get("/{$this->tenant->slug}/materials/course/{$otherCourse->id}/episodes/{$episode->id}/preview")
            ->assertNotFound();
    }
}
