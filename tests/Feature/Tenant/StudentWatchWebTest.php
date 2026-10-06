<?php

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeProgress;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The student Watch pages on the web, which share WatchCatalog with the mobile API.
 */
class StudentWatchWebTest extends ApiTestCase
{
    private Tenant $tenant;

    private User $student;

    private Course $course;

    private CourseSeries $series;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant();
        $lecturer = $this->createMember($this->tenant, 'lecturer', ['name' => 'Dr Hidayat']);
        $this->student = $this->createMember($this->tenant, 'student');
        $this->course = $this->createCourse($this->tenant, $lecturer, ['code' => 'BTG3333', 'title' => 'Process Piping']);
        $this->enroll($this->createSection($this->course), $this->student);
        $this->series = CourseSeries::create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $this->course->id,
            'title' => 'Titis: A Piping Story',
            'tagline' => 'One drop of oil, fourteen weeks of piping.',
        ]);
    }

    private function episode(int $number, array $attributes = []): Episode
    {
        return Episode::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'course_series_id' => $this->series->id,
            'course_id' => $this->course->id,
            'episode_number' => $number,
            'week_number' => $number + 1,
            'title' => "Episode {$number}",
            'status' => Episode::STATUS_PUBLISHED,
            'source' => Episode::SOURCE_YOUTUBE,
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'video_disk' => 'local',
            'duration_seconds' => 275,
        ], $attributes));
    }

    private function url(string $path = ''): string
    {
        return "/{$this->tenant->slug}/watch{$path}";
    }

    public function test_watch_home_shows_the_billboard_and_rows(): void
    {
        $ep1 = $this->episode(1, ['title' => 'Titis Leaves Home']);
        $this->episode(2, ['title' => 'The Book of Laws']);
        $this->episode(3, ['title' => 'Draft cut', 'status' => Episode::STATUS_DRAFT]);
        EpisodeProgress::create(['episode_id' => $ep1->id, 'user_id' => $this->student->id, 'position_seconds' => 100, 'furthest_seconds' => 100, 'last_watched_at' => now()]);

        $this->actingAs($this->student)->get($this->url())
            ->assertOk()
            ->assertSee('Continue watching')
            ->assertSee('New episodes')
            ->assertSee('Titis Leaves Home')
            ->assertSee('The Book of Laws')
            ->assertSee('Titis: A Piping Story · BTG3333')
            ->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
            ->assertDontSee('Draft cut');
    }

    public function test_watch_home_has_an_empty_state(): void
    {
        $this->actingAs($this->student)->get($this->url())
            ->assertOk()
            ->assertSee('Nothing to watch yet');
    }

    public function test_series_page_lists_episodes_and_locks_scheduled_ones(): void
    {
        $this->episode(1, ['title' => 'Titis Leaves Home']);
        $this->episode(2, ['title' => 'Build It Like LEGO', 'publish_at' => now()->addWeek()]);

        $this->actingAs($this->student)->get($this->url("/series/{$this->series->id}"))
            ->assertOk()
            ->assertSee('One drop of oil, fourteen weeks of piping.')
            ->assertSee('Play EP 1')
            ->assertSee('0 of 2 watched')
            ->assertSee('Unlocks');
    }

    public function test_player_page_carries_what_the_player_needs(): void
    {
        $episode = $this->episode(1, ['title' => 'Titis Leaves Home']);
        $episode->scenes()->create(['code' => 'S01', 'title' => 'Meet Titis', 'start_seconds' => 0]);
        $check = $episode->checks()->create(['at_seconds' => 167, 'prompt' => 'Which one is piping?']);
        $check->options()->create(['label' => 'Pipe hanger', 'is_correct' => true]);
        $next = $this->episode(2, ['title' => 'The Book of Laws']);

        $this->actingAs($this->student)->get($this->url("/episodes/{$episode->id}"))
            ->assertOk()
            ->assertSee('id="watch-yt"', false)
            ->assertSee('Meet Titis')
            ->assertSee('Which one is piping?')
            ->assertSee('EP 2 · The Book of Laws')
            ->assertSee("/watch/episodes/{$next->id}", false)
            ->assertDontSee('is_correct&quot;:true', false);
    }

    public function test_uploaded_episodes_play_from_the_signed_stream(): void
    {
        $episode = $this->episode(1, ['source' => Episode::SOURCE_UPLOAD, 'youtube_video_id' => null, 'video_path' => 'episodes/ep1.mp4']);

        $this->actingAs($this->student)->get($this->url("/episodes/{$episode->id}"))
            ->assertOk()
            ->assertSee('<video', false)
            ->assertSee("/api/v1/watch/episodes/{$episode->id}/stream?expires=", false);
    }

    public function test_progress_and_answers_save_like_the_app(): void
    {
        $episode = $this->episode(1);
        $check = $episode->checks()->create(['at_seconds' => 167, 'prompt' => 'Which one is piping?']);
        $wrong = $check->options()->create(['label' => 'Pump casing', 'is_correct' => false]);
        $right = $check->options()->create(['label' => 'Pipe hanger', 'is_correct' => true]);

        $this->actingAs($this->student)
            ->postJson($this->url("/episodes/{$episode->id}/progress"), ['position_seconds' => 120, 'rewinds' => [['from_seconds' => 120, 'to_seconds' => 100]]])
            ->assertOk()
            ->assertJsonPath('data.position_seconds', 120)
            ->assertJsonPath('data.watched_percent', 44);

        // The page saves on leaving with a form-encoded beacon.
        $this->actingAs($this->student)
            ->post($this->url("/episodes/{$episode->id}/progress"), ['position_seconds' => 275, 'completed' => '1'])
            ->assertOk()
            ->assertJsonPath('data.completed', true);

        $this->actingAs($this->student)
            ->postJson($this->url("/checks/{$check->id}/answer"), ['option_id' => $wrong->id])
            ->assertOk()
            ->assertJsonPath('data.is_correct', false)
            ->assertJsonPath('data.correct_option_id', $right->id);

        $this->assertSame(1, $episode->rewinds()->count());
    }

    public function test_students_outside_the_course_are_refused(): void
    {
        $episode = $this->episode(1);
        $outsider = $this->createMember($this->tenant, 'student');

        $this->actingAs($outsider)->get($this->url("/episodes/{$episode->id}"))->assertForbidden();
        $this->actingAs($outsider)->get($this->url("/series/{$this->series->id}"))->assertForbidden();
        $this->actingAs($outsider)->postJson($this->url("/episodes/{$episode->id}/progress"), ['position_seconds' => 5])->assertForbidden();
    }

    public function test_dashboard_shows_the_watch_banner(): void
    {
        $this->episode(1, ['title' => 'Titis Leaves Home']);

        $this->actingAs($this->student)->get("/{$this->tenant->slug}/dashboard")
            ->assertOk()
            ->assertSee('Watch · Titis: A Piping Story')
            ->assertSee('EP 1 · Titis Leaves Home');
    }
}
