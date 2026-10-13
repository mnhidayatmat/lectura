<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeClip;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentWatchClipsApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function episode(Course $course, int $number, array $attributes = []): Episode
    {
        $series = CourseSeries::firstOrCreate(
            ['course_id' => $course->id],
            ['tenant_id' => $course->tenant_id, 'title' => 'Titis: A Piping Story'],
        );

        return Episode::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_series_id' => $series->id,
            'course_id' => $course->id,
            'episode_number' => $number,
            'week_number' => $number + 1,
            'title' => "Episode {$number}",
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => "episodes/{$course->id}/ep{$number}.mp4",
        ], $attributes));
    }

    private function clip(Episode $episode, int $number, array $attributes = []): EpisodeClip
    {
        return EpisodeClip::create(array_merge([
            'tenant_id' => $episode->tenant_id,
            'episode_id' => $episode->id,
            'course_id' => $episode->course_id,
            'clip_number' => $number,
            'title' => "Clip {$number}",
            'topic' => 'Source, piping, end user',
            'takeaway' => 'Fluid flows source to piping system to end user.',
            'start_seconds' => 37,
            'duration_seconds' => 44,
            'video_disk' => 'local',
            'video_path' => "episodes/{$episode->course_id}/clips/ep{$episode->episode_number}-{$number}.mp4",
        ], $attributes));
    }

    public function test_lists_clips_of_available_episodes_in_episode_order(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $ep2 = $this->episode($course, 2);
        $ep1 = $this->episode($course, 1);
        $this->clip($ep2, 1);
        $this->clip($ep1, 2);
        $first = $this->clip($ep1, 1, ['title' => "Pipes Are the Plant's Arteries"]);
        $this->clip($this->episode($course, 3, ['publish_at' => now()->addWeek()]), 1);
        $this->clip($this->episode($course, 4, ['status' => Episode::STATUS_LOCKED]), 1);
        $this->clip($this->episode($course, 5, ['status' => Episode::STATUS_DRAFT]), 1);

        $response = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch/clips'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.title', "Pipes Are the Plant's Arteries")
            ->assertJsonPath('data.0.course.code', 'SKMM1203')
            ->assertJsonPath('data.0.episode.id', $ep1->id)
            ->assertJsonPath('data.0.episode.episode_number', 1)
            ->assertJsonPath('data.0.takeaway', 'Fluid flows source to piping system to end user.')
            ->assertJsonPath('data.0.start_seconds', 37)
            ->assertJsonPath('data.0.poster_url', null)
            ->assertJsonPath('data.1.clip_number', 2)
            ->assertJsonPath('data.2.episode.episode_number', 2);

        $this->assertStringContainsString("/api/v1/watch/clips/{$first->id}/stream?expires=", $response->json('data.0.stream_url'));
    }

    public function test_filters_by_course_and_refuses_other_courses(): void
    {
        [$tenant, $lecturer, $student, $course] = $this->enrolledStudent();
        $this->clip($this->episode($course, 1), 1);
        $other = $this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']);
        $this->clip($this->episode($other, 1), 1);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch/clips'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/clips?course_id={$course->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/clips?course_id={$other->id}"))
            ->assertForbidden();
    }

    public function test_signed_clip_stream_serves_the_file_and_rejects_unsigned_links(): void
    {
        Storage::fake('local');
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $clip = $this->clip($this->episode($course, 1), 1);
        Storage::disk('local')->put($clip->video_path, '0123456789abcdef');

        $url = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch/clips'))->json('data.0.stream_url');

        $response = $this->withHeaders(['Range' => 'bytes=0-9', 'Authorization' => ''])->get($url);
        $response->assertStatus(206);
        $this->assertSame('0123456789', $response->streamedContent());

        $this->get("/api/v1/watch/clips/{$clip->id}/stream")->assertForbidden();
    }

    public function test_import_command_creates_then_updates_clips_from_a_manifest(): void
    {
        Storage::fake('local');
        config(['lectura.episodes.disk' => 'local']);
        [, , , $course] = $this->enrolledStudent();
        $episode = $this->episode($course, 1);

        $dir = sys_get_temp_dir().'/clips-'.uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/a.mp4", 'video');
        file_put_contents("{$dir}/a.jpg", 'poster');
        $manifest = [
            ['episode_number' => 1, 'clip_number' => 1, 'title' => 'One', 'topic' => 'T', 'takeaway' => 'K', 'start_seconds' => 37.1, 'duration_seconds' => 44.13, 'file' => 'a.mp4', 'poster' => 'a.jpg'],
            ['episode_number' => 1, 'clip_number' => 2, 'title' => 'Not rendered', 'file' => 'missing.mp4'],
            ['episode_number' => 9, 'clip_number' => 1, 'title' => 'No episode', 'file' => 'a.mp4'],
        ];
        file_put_contents("{$dir}/manifest.json", json_encode($manifest));

        $this->artisan('watch:import-clips', ['course' => $course->id, 'dir' => $dir])
            ->expectsOutputToContain('1 created, 0 updated, 2 skipped')
            ->assertSuccessful();

        $clip = EpisodeClip::firstOrFail();
        $this->assertSame([$episode->id, 37, 44, 'One'], [$clip->episode_id, $clip->start_seconds, $clip->duration_seconds, $clip->title]);
        Storage::disk('local')->assertExists([$clip->video_path, $clip->poster_path]);

        $manifest[0]['title'] = 'One, renamed';
        file_put_contents("{$dir}/manifest.json", json_encode($manifest));
        $this->artisan('watch:import-clips', ['course' => $course->id, 'dir' => $dir])
            ->expectsOutputToContain('0 created, 1 updated, 2 skipped')
            ->assertSuccessful();

        $this->assertSame(1, EpisodeClip::count());
        $this->assertSame(['One, renamed', $clip->video_path], [$clip->fresh()->title, $clip->fresh()->video_path]);
    }
}
