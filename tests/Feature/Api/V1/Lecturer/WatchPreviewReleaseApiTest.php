<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCheckAnswer;
use App\Models\EpisodeProgress;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EpisodePublished;
use Illuminate\Support\Facades\Notification;

/**
 * "Preview as student" and release controls for lecturers in the app.
 */
class WatchPreviewReleaseApiTest extends LecturerApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant(['timezone' => 'Asia/Kuala_Lumpur']);
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3333']);
        $this->student = $this->createMember($this->tenant, 'student');
        $this->enroll($this->createSection($this->course, [], [$this->lecturer]), $this->student);
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
            'week_number' => 2,
            'title' => 'I Woke Up as an Oil Slime',
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => 'episodes/ep1.mp4',
            'duration_seconds' => 200,
        ], $attributes));
    }

    private function api(string $path): string
    {
        return $this->tenantApi($this->tenant, $path);
    }

    public function test_preview_shows_the_series_as_students_see_it(): void
    {
        $this->episode();
        $locked = $this->episode(['episode_number' => 2, 'title' => 'Plot, Plan and Isometric', 'status' => Episode::STATUS_LOCKED]);
        $this->episode(['episode_number' => 3, 'title' => 'Draft one', 'status' => Episode::STATUS_DRAFT]);

        // The lecturer is not enrolled, yet sees the student series: drafts hidden, locked closed.
        $this->actingAsApi($this->lecturer)->getJson($this->api("lecturer/courses/{$this->course->id}/watch/preview"))
            ->assertOk()
            ->assertJsonCount(2, 'data.episodes')
            ->assertJsonPath('data.episodes.1.id', $locked->id)
            ->assertJsonPath('data.episodes.1.is_available', false)
            ->assertJsonPath('data.episodes.1.available_at', null)
            ->assertJsonPath('data.completed_count', 0);
    }

    public function test_preview_plays_any_episode_with_answers_and_saves_nothing(): void
    {
        $locked = $this->episode(['status' => Episode::STATUS_LOCKED]);
        $check = $locked->checks()->create(['at_seconds' => 120, 'prompt' => 'Which is part of piping?', 'explanation' => 'Supports are piping.']);
        $right = null;
        foreach (['Building frame', 'Pipe hanger', 'Pump casing'] as $i => $label) {
            $option = $check->options()->create(['label' => $label, 'is_correct' => $i === 1, 'sort_order' => $i]);
            $right = $i === 1 ? $option : $right;
        }

        $this->actingAsApi($this->lecturer)->getJson($this->api("lecturer/watch/episodes/{$locked->id}/preview"))
            ->assertOk()
            ->assertJsonPath('data.preview', true)
            ->assertJsonPath('data.can_download', false)
            ->assertJsonPath('data.checks.0.correct_option_id', $right->id)
            ->assertJsonPath('data.checks.0.explanation', 'Supports are piping.');

        // Students still cannot open it, and never get the answers up front.
        $this->actingAsApi($this->student)->getJson($this->api("student/watch/episodes/{$locked->id}"))->assertForbidden();
        $open = $this->episode(['episode_number' => 2]);
        $open->checks()->create(['at_seconds' => 10, 'prompt' => 'Q?'])->options()->create(['label' => 'A', 'is_correct' => true]);
        $this->actingAsApi($this->student)->getJson($this->api("student/watch/episodes/{$open->id}"))
            ->assertOk()
            ->assertJsonMissingPath('data.checks.0.correct_option_id')
            ->assertJsonMissingPath('data.preview');

        $this->assertSame(0, EpisodeProgress::count());
        $this->assertSame(0, EpisodeCheckAnswer::count());
    }

    public function test_release_locks_schedules_and_publishes(): void
    {
        Notification::fake();
        $episode = $this->episode(['status' => Episode::STATUS_DRAFT, 'notify_students' => false]);
        $release = fn (array $body) => $this->actingAsApi($this->lecturer)->patchJson($this->api("lecturer/watch/episodes/{$episode->id}/release"), $body);

        $release(['status' => 'locked'])
            ->assertOk()
            ->assertJsonPath('message', 'Episode 1 is locked. Students see it as coming soon.')
            ->assertJsonPath('data.status', 'locked')
            ->assertJsonPath('data.available_at', null);

        $release(['status' => 'locked', 'publish_at' => '2026-11-02T08:00:00+08:00', 'notify_students' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Episode 1 unlocks Mon 2 Nov, 8:00 AM.')
            ->assertJsonPath('data.publish_at', '2026-11-02T00:00:00+00:00')
            ->assertJsonPath('data.notify_students', true)
            ->assertJsonPath('data.is_available', false);
        Notification::assertNothingSent();

        $release(['status' => 'published', 'publish_at' => null])
            ->assertOk()
            ->assertJsonPath('message', 'Episode 1 is released. Students were notified.')
            ->assertJsonPath('data.is_available', true);
        Notification::assertSentToTimes($this->student, EpisodePublished::class, 1);

        $release(['status' => 'draft'])->assertJsonPath('message', "Episode 1 is a draft. Students can't see it.");
    }

    public function test_release_validates_and_refuses_other_people(): void
    {
        $episode = $this->episode();
        $stranger = $this->createMember($this->tenant, 'lecturer');

        $this->actingAsApi($this->lecturer)->patchJson($this->api("lecturer/watch/episodes/{$episode->id}/release"), ['status' => 'hidden', 'publish_at' => 'soon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'publish_at']);
        $this->actingAsApi($stranger)->patchJson($this->api("lecturer/watch/episodes/{$episode->id}/release"), ['status' => 'locked'])
            ->assertForbidden();
        $this->actingAsApi($this->student)->getJson($this->api("lecturer/courses/{$this->course->id}/watch/preview"))
            ->assertForbidden();
        $this->assertSame(Episode::STATUS_PUBLISHED, $episode->fresh()->status);
    }
}
