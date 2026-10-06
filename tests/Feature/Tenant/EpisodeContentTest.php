<?php

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EpisodePublished;
use App\Notifications\EpisodeReminder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * Scenes, Quick Checks, captions and release notifications on the lecturer's web pages.
 */
class EpisodeContentTest extends ApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->tenant = $this->createTenant(['timezone' => 'Asia/Kuala_Lumpur']);
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3333']);
    }

    private function base(string $path = ''): string
    {
        return "/{$this->tenant->slug}/materials/course/{$this->course->id}/episodes{$path}";
    }

    private function episode(array $attributes = []): Episode
    {
        $series = CourseSeries::firstOrCreate(
            ['course_id' => $this->course->id],
            ['tenant_id' => $this->tenant->id, 'title' => 'Titis: A Piping Story'],
        );

        return Episode::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'course_series_id' => $series->id,
            'course_id' => $this->course->id,
            'episode_number' => 1,
            'title' => 'Titis Leaves Home',
            'status' => Episode::STATUS_DRAFT,
            'video_disk' => 'local',
            'video_path' => 'episodes/ep1.mp4',
            'duration_seconds' => 275,
        ], $attributes));
    }

    public function test_importing_the_storyboard_saves_scenes_and_drafts_the_check(): void
    {
        $episode = $this->episode();
        $storyboard = implode("\n", [
            '| # | Time | Visual | On-screen text | Narration |',
            '|---|---|---|---|---|',
            '| S01 | 0:00–0:14 | Night sea. | **Meet Titis** | Meet Titis… |',
            '| S09 | 2:47–3:00 | A quiz card with three options: Building frame / Pipe hanger / Pump casing. Then "Pipe hanger" turns green. | Quick Check | Quick check!… |',
        ]);

        $this->actingAs($this->lecturer)
            ->put($this->base("/{$episode->id}/scenes"), ['scenes' => $storyboard])
            ->assertRedirect()
            ->assertSessionHas('success', '2 scenes saved.')
            ->assertSessionHas('check_suggestion', fn ($s) => $s['at_seconds'] === 167 && $s['correct_index'] === 1);

        $this->assertSame(['Meet Titis', 'Quick Check'], $episode->scenes()->pluck('title')->all());

        $this->actingAs($this->lecturer)
            ->withSession(['check_suggestion' => ['at_seconds' => 167, 'prompt' => null, 'options' => ['Building frame', 'Pipe hanger', 'Pump casing'], 'correct_index' => 1]])
            ->get($this->base("/{$episode->id}"))
            ->assertOk()
            ->assertSee('Add the Quick Check found in the storyboard')
            ->assertSee('value="2:47"', false)
            ->assertSee('value="Pipe hanger"', false);
    }

    public function test_rejects_text_with_no_scenes(): void
    {
        $episode = $this->episode();

        $this->actingAs($this->lecturer)
            ->put($this->base("/{$episode->id}/scenes"), ['scenes' => 'just some notes'])
            ->assertSessionHasErrors('scenes');
    }

    public function test_adds_and_edits_a_quick_check_keeping_the_marked_answer(): void
    {
        $episode = $this->episode();

        $this->actingAs($this->lecturer)->post($this->base("/{$episode->id}/checks"), [
            'at' => '2:47',
            'prompt' => 'Which of these counts as piping under B31.3?',
            'options' => ['Building frame', '', 'Pipe hanger', 'Pump casing'],
            'correct' => 2,
        ])->assertSessionHasNoErrors();

        $check = $episode->checks()->with('options')->sole();
        $this->assertSame(167, $check->at_seconds);
        $this->assertSame(['Building frame', 'Pipe hanger', 'Pump casing'], $check->options->pluck('label')->all());
        $this->assertSame('Pipe hanger', $check->options->firstWhere('is_correct', true)->label);

        $this->actingAs($this->lecturer)->patch($this->base("/{$episode->id}/checks/{$check->id}"), [
            'at' => '2:50',
            'prompt' => 'Which one is piping?',
            'options' => ['Pipe hanger', 'Pump casing'],
            'correct' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(170, $check->fresh()->at_seconds);
        $this->assertSame(2, $check->options()->count());
    }

    public function test_quick_check_needs_a_filled_correct_option(): void
    {
        $episode = $this->episode();

        $this->actingAs($this->lecturer)->post($this->base("/{$episode->id}/checks"), [
            'at' => '2:47',
            'prompt' => 'Which one?',
            'options' => ['A', 'B', ''],
            'correct' => 2,
        ])->assertSessionHasErrors('correct');

        $this->actingAs($this->lecturer)->post($this->base("/{$episode->id}/checks"), [
            'at' => 'soon',
            'prompt' => 'Which one?',
            'options' => ['A', 'B'],
            'correct' => 0,
        ])->assertSessionHasErrors('at');

        $this->assertSame(0, $episode->checks()->count());
    }

    public function test_srt_captions_are_converted_to_webvtt(): void
    {
        $episode = $this->episode();
        $srt = "1\r\n00:00:00,000 --> 00:00:02,500\r\nMeet Titis.\r\n";

        $this->actingAs($this->lecturer)->post($this->base("/{$episode->id}/captions"), [
            'language' => 'ms',
            'file' => UploadedFile::fake()->createWithContent('ep1.srt', $srt),
        ])->assertSessionHasNoErrors();

        $caption = $episode->captions()->sole();
        $this->assertSame('ms', $caption->language);
        $this->assertSame("WEBVTT\n\n1\n00:00:00.000 --> 00:00:02.500\nMeet Titis.\n", Storage::disk('local')->get($caption->path));

        $this->actingAs($this->lecturer)->post($this->base("/{$episode->id}/captions"), [
            'language' => 'en',
            'file' => UploadedFile::fake()->createWithContent('notes.vtt', 'nothing here'),
        ])->assertSessionHasErrors('file');
    }

    public function test_publishing_notifies_enrolled_students_once(): void
    {
        Notification::fake();
        $student = $this->createMember($this->tenant, 'student');
        $outsider = $this->createMember($this->tenant, 'student');
        $this->enroll($this->createSection($this->course), $student);
        $episode = $this->episode();

        $publish = fn () => $this->actingAs($this->lecturer)->patch($this->base("/{$episode->id}"), [
            'title' => 'Titis Leaves Home',
            'episode_number' => 1,
            'status' => 'published',
            'notify_students' => '1',
            'required_by' => '2026-10-14T08:00',
        ]);

        $publish()->assertSessionHas('success', 'Episode 1 saved. Students were notified.');
        $publish()->assertSessionHas('success', 'Episode 1 saved.');

        Notification::assertSentToTimes($student, EpisodePublished::class, 1);
        Notification::assertNotSentTo($outsider, EpisodePublished::class);
        Notification::assertSentTo($student, EpisodePublished::class, function (EpisodePublished $n) use ($student, $episode) {
            $data = $n->toArray($student);

            return $data['type'] === 'episode_published'
                && $data['episode_id'] === $episode->id
                && $data['series_id'] === $episode->course_series_id
                && str_contains($data['message'], 'Watch it before Wed 14 Oct, 8:00 AM.');
        });
    }

    public function test_scheduled_episodes_are_announced_by_the_scheduler_when_released(): void
    {
        Notification::fake();
        $student = $this->createMember($this->tenant, 'student');
        $this->enroll($this->createSection($this->course), $student);
        $episode = $this->episode(['status' => Episode::STATUS_PUBLISHED, 'publish_at' => now()->addHour()]);
        $this->episode(['episode_number' => 2, 'status' => Episode::STATUS_PUBLISHED, 'notify_students' => false]);

        $this->artisan('episodes:announce')->expectsOutput('0 episode(s) announced.')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(2)->hours();
        $this->artisan('episodes:announce')->expectsOutput('1 episode(s) announced.')->assertSuccessful();
        $this->artisan('episodes:announce')->expectsOutput('0 episode(s) announced.')->assertSuccessful();

        Notification::assertSentToTimes($student, EpisodePublished::class, 1);
        $this->assertNotNull($episode->fresh()->announced_at);
    }

    public function test_episode_page_shows_how_students_watched_and_sends_reminders(): void
    {
        Notification::fake();
        $student = $this->createMember($this->tenant, 'student', ['name' => 'Zul Hakim']);
        $this->enroll($this->createSection($this->course), $student);
        $episode = $this->episode(['status' => Episode::STATUS_PUBLISHED]);

        $this->actingAs($this->lecturer)->get($this->base("/{$episode->id}"))
            ->assertOk()
            ->assertSee('How your students watched')
            ->assertSee('Not started (1)')
            ->assertSee('Zul Hakim');

        $this->actingAs($this->lecturer)->post($this->base("/{$episode->id}/remind"), ['audience' => 'not_started'])
            ->assertSessionHas('success', 'Reminder sent to 1 student.');
        $this->actingAs($this->lecturer)->post($this->base("/{$episode->id}/remind"), ['audience' => 'not_started'])
            ->assertSessionHasErrors('audience');

        Notification::assertSentToTimes($student, EpisodeReminder::class, 1);
    }
}
