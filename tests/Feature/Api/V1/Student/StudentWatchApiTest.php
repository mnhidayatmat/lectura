<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\CourseSeries;
use App\Models\CourseTopic;
use App\Models\Episode;
use App\Models\EpisodeProgress;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentWatchApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function series(Course $course, array $attributes = []): CourseSeries
    {
        return CourseSeries::create(array_merge([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'title' => 'Titis: A Piping Story',
            'tagline' => 'One drop of oil, fourteen weeks of piping.',
        ], $attributes));
    }

    private function episode(CourseSeries $series, int $number, array $attributes = []): Episode
    {
        return Episode::create(array_merge([
            'tenant_id' => $series->tenant_id,
            'course_series_id' => $series->id,
            'course_id' => $series->course_id,
            'episode_number' => $number,
            'week_number' => $number + 1,
            'title' => "Episode {$number}",
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => "episodes/{$series->course_id}/ep{$number}.mp4",
            'video_mime' => 'video/mp4',
            'video_size_bytes' => 4096,
            'duration_seconds' => 200,
        ], $attributes));
    }

    private function progress(Episode $episode, User $user, array $attributes = []): EpisodeProgress
    {
        return EpisodeProgress::create(array_merge([
            'episode_id' => $episode->id,
            'user_id' => $user->id,
            'position_seconds' => 100,
            'furthest_seconds' => 100,
            'last_watched_at' => now(),
        ], $attributes));
    }

    public function test_home_lists_series_rows_and_hides_drafts(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $series = $this->series($course);
        $ep1 = $this->episode($series, 1);
        $this->episode($series, 2);
        $this->episode($series, 3, ['status' => Episode::STATUS_DRAFT]);
        $this->episode($series, 4, ['publish_at' => now()->addWeek()]);
        $this->progress($ep1, $student);

        $response = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch'))
            ->assertOk()
            ->assertJsonCount(1, 'data.series')
            ->assertJsonPath('data.series.0.title', 'Titis: A Piping Story')
            ->assertJsonPath('data.series.0.lecturer_name', 'Dr Hidayat')
            ->assertJsonPath('data.series.0.episodes_count', 3)
            ->assertJsonPath('data.series.0.available_count', 2)
            ->assertJsonCount(3, 'data.series.0.episodes')
            ->assertJsonPath('data.series.0.episodes.2.is_available', false)
            ->assertJsonCount(1, 'data.continue_watching')
            ->assertJsonPath('data.continue_watching.0.id', $ep1->id)
            ->assertJsonPath('data.continue_watching.0.progress.watched_percent', 50)
            ->assertJsonPath('data.continue_watching.0.course.code', 'SKMM1203')
            ->assertJsonCount(1, 'data.new_episodes')
            ->assertJsonPath('data.new_episodes.0.episode_number', 2);

        $this->assertNotNull($response->json('data.featured'));
        $this->assertSame('Titis: A Piping Story', $response->json('data.featured.series_title'));
    }

    public function test_featured_prefers_the_episode_for_the_current_teaching_week(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $course->update(['custom_start_date' => now()->subDays(15)->toDateString()]); // week 3
        $series = $this->series($course);
        $this->episode($series, 1, ['week_number' => 2]);
        $week3 = $this->episode($series, 2, ['week_number' => 3, 'publish_at' => now()->subDays(20)]);
        $this->episode($series, 3, ['week_number' => 4]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch'))
            ->assertOk()
            ->assertJsonPath('data.featured.id', $week3->id)
            ->assertJsonPath('data.series.0.current_week', 3);
    }

    public function test_home_is_empty_for_courses_the_student_is_not_in(): void
    {
        [$tenant, $lecturer, $student] = $this->enrolledStudent();
        $other = $this->createCourse($tenant, $lecturer, ['code' => 'BTG3333']);
        $this->episode($this->series($other), 1);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch'))
            ->assertOk()
            ->assertJsonPath('data.featured', null)
            ->assertJsonCount(0, 'data.series');
    }

    public function test_series_page_has_outcomes_and_up_next(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        CourseLearningOutcome::create(['course_id' => $course->id, 'code' => 'CLO1', 'description' => 'Explain the scope of ASME B31.3.', 'sort_order' => 0]);
        $topic = CourseTopic::create(['course_id' => $course->id, 'week_number' => 2, 'title' => 'Introduction to piping']);
        $series = $this->series($course);
        $ep1 = $this->episode($series, 1, ['course_topic_id' => $topic->id]);
        $ep2 = $this->episode($series, 2);
        $this->progress($ep1, $student, ['completed_at' => now()]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/series/{$series->id}"))
            ->assertOk()
            ->assertJsonPath('data.completed_count', 1)
            ->assertJsonPath('data.learning_outcomes.0.code', 'CLO1')
            ->assertJsonPath('data.up_next.id', $ep2->id)
            ->assertJsonPath('data.episodes.0.topic.title', 'Introduction to piping')
            ->assertJsonPath('data.episodes.0.progress.completed', true)
            ->assertJsonPath('data.episodes.0.progress.watched_percent', 100);
    }

    public function test_series_requires_enrollment(): void
    {
        [$tenant, $lecturer] = $this->enrolledStudent();
        $outsider = $this->createMember($tenant, 'student');
        $course = $this->createCourse($tenant, $lecturer);
        $series = $this->series($course);
        $this->episode($series, 1);

        $this->actingAsApi($outsider)->getJson($this->tenantApi($tenant, "student/watch/series/{$series->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not enrolled in this course.');
    }

    public function test_episode_detail_gives_a_signed_stream_and_the_next_episode(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $series = $this->series($course);
        $ep1 = $this->episode($series, 1);
        $ep2 = $this->episode($series, 2);

        $response = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/episodes/{$ep1->id}"))
            ->assertOk()
            ->assertJsonPath('data.series.title', 'Titis: A Piping Story')
            ->assertJsonPath('data.mime_type', 'video/mp4')
            ->assertJsonPath('data.next_episode.id', $ep2->id)
            ->assertJsonPath('data.progress', null);

        $this->assertStringContainsString("/api/v1/watch/episodes/{$ep1->id}/stream?expires=", $response->json('data.stream_url'));
        $this->assertStringContainsString('signature=', $response->json('data.stream_url'));
    }

    public function test_drafts_are_not_found_and_scheduled_episodes_are_locked(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $series = $this->series($course);
        $draft = $this->episode($series, 1, ['status' => Episode::STATUS_DRAFT]);
        $scheduled = $this->episode($series, 2, ['publish_at' => now()->addDay()]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/episodes/{$draft->id}"))
            ->assertNotFound();

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/episodes/{$scheduled->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'This episode is not available yet.');

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "student/watch/episodes/{$scheduled->id}/progress"), ['position_seconds' => 5])
            ->assertForbidden();
    }

    public function test_episodes_of_another_institution_are_not_found(): void
    {
        [$tenant, , $student] = $this->enrolledStudent();
        $otherTenant = $this->createTenant();
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');
        $foreign = $this->episode($this->series($this->createCourse($otherTenant, $otherLecturer)), 1);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/episodes/{$foreign->id}"))
            ->assertNotFound();
    }

    public function test_progress_saves_position_and_completion_is_sticky(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $episode = $this->episode($this->series($course), 1, ['duration_seconds' => null]);
        $url = $this->tenantApi($tenant, "student/watch/episodes/{$episode->id}/progress");

        $this->actingAsApi($student)->postJson($url, ['position_seconds' => 60, 'duration_seconds' => 240])
            ->assertOk()
            ->assertJsonPath('message', 'Progress saved.')
            ->assertJsonPath('data.position_seconds', 60)
            ->assertJsonPath('data.watched_percent', 25)
            ->assertJsonPath('data.completed', false);
        $this->assertSame(240, $episode->fresh()->duration_seconds);

        $this->postJson($url, ['position_seconds' => 220])
            ->assertOk()
            ->assertJsonPath('data.completed', true);

        $this->postJson($url, ['position_seconds' => 10])
            ->assertOk()
            ->assertJsonPath('data.position_seconds', 10)
            ->assertJsonPath('data.completed', true);

        $row = EpisodeProgress::where('episode_id', $episode->id)->sole();
        $this->assertSame(220, $row->furthest_seconds);
    }

    public function test_progress_clamps_to_the_duration_and_validates(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $episode = $this->episode($this->series($course), 1);
        $url = $this->tenantApi($tenant, "student/watch/episodes/{$episode->id}/progress");

        $this->actingAsApi($student)->postJson($url, ['position_seconds' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('position_seconds');

        $this->postJson($url, ['position_seconds' => 9999, 'completed' => true])
            ->assertOk()
            ->assertJsonPath('data.position_seconds', 200)
            ->assertJsonPath('data.completed', true);
    }

    public function test_signed_stream_serves_byte_ranges_and_rejects_tampered_links(): void
    {
        Storage::fake('local');
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $episode = $this->episode($this->series($course), 1);
        Storage::disk('local')->put($episode->video_path, str_repeat('0123456789', 100));

        $streamUrl = $this->actingAsApi($student)
            ->getJson($this->tenantApi($tenant, "student/watch/episodes/{$episode->id}"))
            ->json('data.stream_url');

        // No bearer token: the signature is the authorization.
        $this->app['auth']->forgetGuards();
        $response = $this->withHeaders(['Range' => 'bytes=0-9', 'Authorization' => ''])->get($streamUrl);
        $response->assertStatus(206);
        $response->assertHeader('Content-Range', 'bytes 0-9/1000');
        $this->assertSame('0123456789', $response->streamedContent());

        $this->get(str_replace('signature=', 'signature=x', $streamUrl))->assertForbidden();
        $this->get("/api/v1/watch/episodes/{$episode->id}/stream")->assertForbidden();
    }
}
