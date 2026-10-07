<?php

namespace Tests\Feature\Tenant;

use App\Models\CourseSeries;
use App\Models\CourseTopic;
use App\Models\Episode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The lecturer's web page for uploading and publishing episodes. Extends the API
 * test case for its tenant fixtures; the routes under test are web routes.
 */
class EpisodeManagementTest extends ApiTestCase
{
    public function test_lecturer_uploads_an_episode_and_the_series_is_created(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer, ['title' => 'Process Piping']);
        $topic = CourseTopic::create(['course_id' => $course->id, 'week_number' => 2, 'title' => 'Introduction to piping']);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/materials/course/{$course->id}/episodes")
            ->assertOk()
            ->assertSee('Add an episode');

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/materials/course/{$course->id}/episodes", [
                'title' => 'Titis Leaves Home',
                'episode_number' => 1,
                'week_number' => 2,
                'synopsis' => 'What piping is.',
                'status' => 'published',
                'duration_seconds' => 275,
                'video' => UploadedFile::fake()->create('EP01.mp4', 2048, 'video/mp4'),
                'poster' => UploadedFile::fake()->image('ep1.jpg', 1280, 720),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Episode 1 uploaded.');

        $series = CourseSeries::where('course_id', $course->id)->sole();
        $this->assertSame('Process Piping', $series->title);

        $episode = Episode::sole();
        $this->assertSame($topic->id, $episode->course_topic_id);
        $this->assertSame(275, $episode->duration_seconds);
        $this->assertSame('video/mp4', $episode->video_mime);
        Storage::disk('local')->assertExists($episode->video_path);
        Storage::disk('local')->assertExists($episode->poster_path);
        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/materials/course/{$course->id}/episodes")
            ->assertOk()
            ->assertSee('1. Titis Leaves Home')
            ->assertSee('4:35')
            ->assertSee('0 started');
    }

    public function test_scheduled_release_time_is_read_in_the_institutions_timezone(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenant(['timezone' => 'Asia/Kuala_Lumpur']);
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)->post("/{$tenant->slug}/materials/course/{$course->id}/episodes", [
            'title' => 'Build It Like LEGO',
            'episode_number' => 3,
            'status' => 'published',
            'publish_at' => '2026-10-14T08:00',
            'video' => UploadedFile::fake()->create('EP03.mp4', 512, 'video/mp4'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-14 00:00:00', Episode::sole()->publish_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_lecturer_can_lock_an_episode_as_coming_soon(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)->post("/{$tenant->slug}/materials/course/{$course->id}/episodes", [
            'title' => 'Plot, Plan and Isometric',
            'episode_number' => 8,
            'status' => 'locked',
            'video' => UploadedFile::fake()->create('EP08.mp4', 512, 'video/mp4'),
        ])->assertSessionHasNoErrors();

        $episode = Episode::sole();
        $this->assertSame(Episode::STATUS_LOCKED, $episode->status);
        $this->assertFalse($episode->isAvailable());
        $this->assertNull($episode->availableAt());

        $this->actingAs($lecturer)->get("/{$tenant->slug}/materials/course/{$course->id}/episodes")
            ->assertOk()
            ->assertSee('Locked · coming soon')
            ->assertSee('Locked (coming soon)');
    }

    public function test_rejects_non_video_uploads(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)->post("/{$tenant->slug}/materials/course/{$course->id}/episodes", [
            'title' => 'Slides',
            'episode_number' => 1,
            'status' => 'draft',
            'video' => UploadedFile::fake()->create('slides.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('video');

        $this->assertSame(0, Episode::count());
    }

    public function test_other_lecturers_cannot_manage_the_course_episodes(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $stranger = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);

        $this->actingAs($stranger)
            ->get("/{$tenant->slug}/materials/course/{$course->id}/episodes")
            ->assertForbidden();
    }

    public function test_updating_and_deleting_an_episode_removes_old_files(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $base = "/{$tenant->slug}/materials/course/{$course->id}/episodes";

        $this->actingAs($lecturer)->post($base, [
            'title' => 'Draft cut',
            'episode_number' => 1,
            'status' => 'draft',
            'duration_seconds' => 100,
            'video' => UploadedFile::fake()->create('v1.mp4', 100, 'video/mp4'),
        ]);
        $episode = Episode::sole();
        $oldPath = $episode->video_path;

        $this->actingAs($lecturer)->patch("{$base}/{$episode->id}", [
            'title' => 'Final cut',
            'episode_number' => 1,
            'status' => 'published',
            'video' => UploadedFile::fake()->create('v2.mp4', 100, 'video/mp4'),
        ])->assertSessionHasNoErrors();

        $episode->refresh();
        $this->assertSame('Final cut', $episode->title);
        $this->assertTrue($episode->isAvailable());
        $this->assertNull($episode->duration_seconds);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($episode->video_path);

        $this->actingAs($lecturer)->delete("{$base}/{$episode->id}")->assertRedirect();
        $this->assertSame(0, Episode::count());
        Storage::disk('local')->assertMissing($episode->video_path);
    }

    public function test_lecturer_adds_a_youtube_episode(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)->post("/{$tenant->slug}/materials/course/{$course->id}/episodes", [
            'source' => 'youtube',
            'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ?si=share',
            'length' => '4:35',
            'title' => 'Titis Leaves Home',
            'episode_number' => 1,
            'status' => 'published',
            'allow_download' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Episode 1 added.');

        $episode = Episode::sole();
        $this->assertTrue($episode->isYouTube());
        $this->assertSame('dQw4w9WgXcQ', $episode->youtube_video_id);
        $this->assertSame(275, $episode->duration_seconds);
        $this->assertNull($episode->video_path);
        $this->assertFalse($episode->canDownload());

        $this->actingAs($lecturer)->get("/{$tenant->slug}/materials/course/{$course->id}/episodes")
            ->assertOk()
            ->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', false)
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
        $this->actingAs($lecturer)->get("/{$tenant->slug}/materials/course/{$course->id}/episodes/{$episode->id}")
            ->assertOk()
            ->assertSee('Plays from YouTube');
    }

    public function test_rejects_links_that_are_not_youtube_videos(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);

        $this->actingAs($lecturer)->post("/{$tenant->slug}/materials/course/{$course->id}/episodes", [
            'source' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/@lectura',
            'title' => 'Channel',
            'episode_number' => 1,
            'status' => 'draft',
        ])->assertSessionHasErrors('youtube_url');

        $this->assertSame(0, Episode::count());
    }

    public function test_switching_an_uploaded_episode_to_youtube_deletes_the_file(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $base = "/{$tenant->slug}/materials/course/{$course->id}/episodes";

        $this->actingAs($lecturer)->post($base, [
            'title' => 'Titis Leaves Home',
            'episode_number' => 1,
            'status' => 'draft',
            'duration_seconds' => 275,
            'video' => UploadedFile::fake()->create('EP01.mp4', 100, 'video/mp4'),
        ])->assertSessionHasNoErrors();
        $episode = Episode::sole();
        $file = $episode->video_path;

        $this->actingAs($lecturer)->patch("{$base}/{$episode->id}", [
            'source' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Titis Leaves Home',
            'episode_number' => 1,
            'status' => 'published',
        ])->assertSessionHasNoErrors();

        $episode->refresh();
        $this->assertTrue($episode->isYouTube());
        $this->assertNull($episode->video_path);
        $this->assertNull($episode->duration_seconds);
        Storage::disk('local')->assertMissing($file);

        // Back to an upload needs a file.
        $this->actingAs($lecturer)->patch("{$base}/{$episode->id}", [
            'source' => 'upload',
            'title' => 'Titis Leaves Home',
            'episode_number' => 1,
            'status' => 'published',
        ])->assertSessionHasErrors('video');
    }
}
