<?php

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeClip;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Student\BuildsStudentFixtures;

/**
 * The student web Clips feed (/watch/clips) and the Clips row on /watch.
 */
class StudentWatchClipsWebTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function clip(Course $course, int $episodeNumber, string $title, array $episode = []): EpisodeClip
    {
        $series = CourseSeries::firstOrCreate(
            ['course_id' => $course->id],
            ['tenant_id' => $course->tenant_id, 'title' => "Titis's Slime Journal"],
        );
        $ep = Episode::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_series_id' => $series->id,
            'course_id' => $course->id,
            'episode_number' => $episodeNumber,
            'title' => "Episode {$episodeNumber}",
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => "episodes/ep{$episodeNumber}.mp4",
        ], $episode));

        return EpisodeClip::create([
            'tenant_id' => $course->tenant_id,
            'episode_id' => $ep->id,
            'course_id' => $course->id,
            'clip_number' => 1,
            'title' => $title,
            'topic' => 'Source, piping, end user',
            'takeaway' => 'Fluid flows source to piping system to end user.',
            'video_disk' => 'local',
            'video_path' => "episodes/clips/{$episodeNumber}.mp4",
        ]);
    }

    public function test_feed_lists_clips_of_available_episodes_and_hides_locked_ones(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $open = $this->clip($course, 1, "Pipes Are the Plant's Arteries");
        $this->clip($course, 2, 'A clip from a locked episode', ['status' => Episode::STATUS_LOCKED]);
        $this->clip($course, 3, 'A clip from a scheduled episode', ['publish_at' => now()->addWeek()]);

        $this->actingAs($student)->get("/{$tenant->slug}/watch/clips")
            ->assertOk()
            ->assertSee('Pipes Are the Plant&#039;s Arteries', false)
            ->assertSee('From EP 1 · Episode 1')
            ->assertSee('Fluid flows source to piping system to end user.')
            ->assertSee('Watch episode')
            ->assertSee(route('tenant.watch.episode', [$tenant->slug, $open->episode_id]), false)
            ->assertSee("/api/v1/watch/clips/{$open->id}/stream", false)
            ->assertDontSee('A clip from a locked episode')
            ->assertDontSee('A clip from a scheduled episode');
    }

    public function test_feed_opens_on_the_chosen_clip_and_speaks_malay(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $this->clip($course, 1, 'First');
        $second = $this->clip($course, 2, 'Second');

        $this->actingAs($student)->get("/{$tenant->slug}/watch/clips?clip={$second->id}")
            ->assertOk()
            ->assertSee('clipFeed(1)', false);

        app()->setLocale('ms');
        $this->assertSame('Tonton episod', __('watch.watch_episode'));
        $this->assertSame('Klip', __('watch.clips'));
    }

    public function test_watch_page_shows_a_clips_row_only_when_there_are_clips(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $this->clip($course, 1, 'Locked away', ['status' => Episode::STATUS_LOCKED]);

        $this->actingAs($student)->get("/{$tenant->slug}/watch")
            ->assertOk()
            ->assertDontSee(route('tenant.watch.clips', $tenant->slug).'?clip=', false);

        $clip = $this->clip($course, 2, 'Is a Pipe Hanger Piping?');
        $this->actingAs($student)->get("/{$tenant->slug}/watch")
            ->assertOk()
            ->assertSee(route('tenant.watch.clips', [$tenant->slug, 'clip' => $clip->id]), false)
            ->assertSee('Is a Pipe Hanger Piping?');
    }

    public function test_empty_feed_and_other_courses(): void
    {
        [$tenant, $lecturer, $student] = $this->enrolledStudent();
        $other = $this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']);
        $this->clip($other, 1, 'Not my course');

        $this->actingAs($student)->get("/{$tenant->slug}/watch/clips")
            ->assertOk()
            ->assertSee('No clips yet')
            ->assertDontSee('Not my course');

        $this->actingAs($student)->get("/{$tenant->slug}/watch/clips?course={$other->id}")
            ->assertForbidden();

        // Like /watch, a lecturer is enrolled in nothing, so the feed is simply empty.
        $this->actingAs($lecturer)->get("/{$tenant->slug}/watch/clips")
            ->assertOk()
            ->assertSee('No clips yet');
    }
}
