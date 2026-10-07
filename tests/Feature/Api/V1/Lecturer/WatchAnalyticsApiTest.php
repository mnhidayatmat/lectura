<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCheck;
use App\Models\EpisodeProgress;
use App\Models\EpisodeRewind;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\EpisodeReminder;
use Illuminate\Support\Facades\Notification;

class WatchAnalyticsApiTest extends LecturerApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private Course $course;

    private Section $section;

    /** @var array<int, User> */
    private array $students;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3333']);
        $this->section = $this->createSection($this->course, [], [$this->lecturer]);
        $this->students = [
            $this->createMember($this->tenant, 'student', ['name' => 'Aina Sofea']),
            $this->createMember($this->tenant, 'student', ['name' => 'Bala Kumar']),
            $this->createMember($this->tenant, 'student', ['name' => 'Chong Wei']),
            $this->createMember($this->tenant, 'student', ['name' => 'Zul Hakim']),
        ];
        foreach ($this->students as $student) {
            $this->enroll($this->section, $student);
        }
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
            'week_number' => 2,
            'title' => 'Titis Leaves Home',
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => 'episodes/ep1.mp4',
            'duration_seconds' => 200,
        ], $attributes));
    }

    private function watched(Episode $episode, User $student, int $furthest, bool $completed = false): void
    {
        EpisodeProgress::create([
            'episode_id' => $episode->id,
            'user_id' => $student->id,
            'position_seconds' => $furthest,
            'furthest_seconds' => $furthest,
            'completed_at' => $completed ? now() : null,
            'last_watched_at' => now(),
        ]);
    }

    private function seedViewing(Episode $episode): EpisodeCheck
    {
        $episode->scenes()->createMany([
            ['code' => 'S01', 'title' => 'Meet Titis', 'start_seconds' => 0],
            ['code' => 'S06', 'title' => 'What is a pipe?', 'start_seconds' => 100],
            ['code' => 'S09', 'title' => 'Quick Check', 'start_seconds' => 160],
        ]);
        $check = $episode->checks()->create(['at_seconds' => 160, 'prompt' => 'Which one is piping?']);
        $options = collect(['Building frame', 'Pipe hanger', 'Pump casing'])
            ->map(fn ($label, $i) => $check->options()->create(['label' => $label, 'is_correct' => $i === 1, 'sort_order' => $i]));

        [$aina, $bala, $chong] = $this->students;
        $this->watched($episode, $aina, 200, true);
        $this->watched($episode, $bala, 120);
        $this->watched($episode, $chong, 40);

        $check->answers()->create(['user_id' => $aina->id, 'episode_check_option_id' => $options[1]->id, 'is_correct' => true, 'first_is_correct' => true, 'attempts' => 1, 'answered_at' => now()]);
        $check->answers()->create(['user_id' => $bala->id, 'episode_check_option_id' => $options[1]->id, 'is_correct' => true, 'first_is_correct' => false, 'attempts' => 2, 'answered_at' => now()]);

        foreach ([[130, 105], [140, 101], [150, 110], [30, 2]] as [$from, $to]) {
            EpisodeRewind::create(['episode_id' => $episode->id, 'user_id' => $aina->id, 'from_seconds' => $from, 'to_seconds' => $to]);
        }

        return $check;
    }

    public function test_course_list_summarises_each_episode(): void
    {
        $episode = $this->episode();
        $this->seedViewing($episode);
        $this->episode(['episode_number' => 2, 'title' => 'The Book of Laws', 'status' => Episode::STATUS_DRAFT]);

        $this->actingAsApi($this->lecturer)->getJson($this->tenantApi($this->tenant, "lecturer/courses/{$this->course->id}/watch"))
            ->assertOk()
            ->assertJsonPath('data.series.title', 'Titis: A Piping Story')
            ->assertJsonPath('data.students_count', 4)
            ->assertJsonCount(2, 'data.episodes')
            ->assertJsonPath('data.episodes.0.started', 3)
            ->assertJsonPath('data.episodes.0.finished', 1)
            ->assertJsonPath('data.episodes.0.checks_count', 1)
            ->assertJsonPath('data.episodes.0.first_try_correct_percent', 50)
            ->assertJsonPath('data.episodes.1.status', 'draft');
    }

    public function test_episode_report_has_scene_reach_rewinds_checks_and_students(): void
    {
        $episode = $this->episode();
        $this->seedViewing($episode);

        $this->actingAsApi($this->lecturer)->getJson($this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}"))
            ->assertOk()
            ->assertJsonPath('data.audience', ['students' => 4, 'started' => 3, 'finished' => 1, 'not_started' => 1, 'average_watched_percent' => 60])
            ->assertJsonPath('data.scenes.0.reached_percent', 100)
            ->assertJsonPath('data.scenes.1.reached', 2)
            ->assertJsonPath('data.scenes.1.reached_percent', 67)
            ->assertJsonPath('data.scenes.1.rewinds', 3)
            ->assertJsonPath('data.scenes.2.reached', 1)
            ->assertJsonPath('data.most_rewound_scene', ['code' => 'S06', 'title' => 'What is a pipe?', 'rewinds_per_viewer' => 1])
            ->assertJsonPath('data.checks.0.answered', 2)
            ->assertJsonPath('data.checks.0.first_try_correct_percent', 50)
            ->assertJsonPath('data.checks.0.options.1.chosen', 2)
            ->assertJsonPath('data.checks.0.options.1.is_correct', true)
            ->assertJsonPath('data.students.0.name', 'Zul Hakim')
            ->assertJsonPath('data.students.0.status', 'not_started')
            ->assertJsonPath('data.students.1.name', 'Bala Kumar')
            ->assertJsonPath('data.students.1.watched_percent', 60)
            ->assertJsonPath('data.students.3.status', 'finished')
            ->assertJsonPath('data.reminder.available_at', null);
    }

    public function test_section_lecturers_only_see_their_own_students(): void
    {
        $tutor = $this->createMember($this->tenant, 'lecturer');
        $other = $this->createSection($this->course, ['name' => 'Section 02', 'code' => '02'], [$tutor]);
        $this->enroll($other, $this->createMember($this->tenant, 'student'));
        $episode = $this->episode();
        $this->seedViewing($episode);

        $this->actingAsApi($tutor)->getJson($this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}"))
            ->assertOk()
            ->assertJsonPath('data.audience.students', 1)
            ->assertJsonPath('data.audience.started', 0);
    }

    public function test_report_filters_by_section_and_labels_students_with_theirs(): void
    {
        $this->section->update(['name' => 'Section 01']);
        $second = $this->createSection($this->course, ['name' => 'Section 02', 'code' => '02'], [$this->lecturer]);
        $this->enroll($second, $this->createMember($this->tenant, 'student', ['name' => 'Yusri Amin']));
        $foreign = $this->createSection($this->course, ['name' => 'Section 03', 'code' => '03'], [$this->createMember($this->tenant, 'lecturer')]);
        $episode = $this->episode();
        $this->seedViewing($episode);
        $url = $this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}");

        $this->actingAsApi($this->lecturer)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.section_id', null)
            ->assertJsonPath('data.sections', [
                ['id' => $this->section->id, 'name' => 'Section 01', 'students' => 4],
                ['id' => $second->id, 'name' => 'Section 02', 'students' => 1],
            ])
            ->assertJsonPath('data.audience.students', 5)
            ->assertJsonPath('data.students.0.name', 'Yusri Amin')
            ->assertJsonPath('data.students.0.section_name', 'Section 02');

        $this->getJson($url.'?section_id='.$second->id)
            ->assertOk()
            ->assertJsonPath('data.section_id', $second->id)
            ->assertJsonPath('data.audience', ['students' => 1, 'started' => 0, 'finished' => 0, 'not_started' => 1, 'average_watched_percent' => 0])
            ->assertJsonPath('data.episode.started', 0)
            ->assertJsonPath('data.checks.0.answered', 0)
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.section_name', null);

        $this->getJson($url.'?section_id='.$foreign->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('section_id');
    }

    public function test_reminder_can_target_one_section(): void
    {
        Notification::fake();
        $second = $this->createSection($this->course, ['name' => 'Section 02', 'code' => '02'], [$this->lecturer]);
        $yusri = $this->createMember($this->tenant, 'student');
        $this->enroll($second, $yusri);
        $episode = $this->episode();

        $this->actingAsApi($this->lecturer)
            ->postJson($this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}/remind"), ['section_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('data.sent', 1);

        Notification::assertSentTo($yusri, EpisodeReminder::class);
        Notification::assertNotSentTo($this->students, EpisodeReminder::class);
    }

    public function test_students_and_strangers_are_refused(): void
    {
        $episode = $this->episode();
        $stranger = $this->createMember($this->tenant, 'lecturer');

        $this->actingAsApi($this->students[0])->getJson($this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}"))
            ->assertForbidden();
        $this->actingAsApi($stranger)->getJson($this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'You do not have access to this course.');
    }

    public function test_reminder_goes_to_the_chosen_audience_once_an_hour(): void
    {
        Notification::fake();
        $episode = $this->episode();
        $this->seedViewing($episode);
        [$aina, $bala, $chong, $zul] = $this->students;
        $url = $this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}/remind");

        $this->actingAsApi($this->lecturer)->postJson($url)
            ->assertOk()
            ->assertJsonPath('message', 'Reminder sent to 1 student.')
            ->assertJsonPath('data.sent', 1);
        Notification::assertSentTo($zul, EpisodeReminder::class, fn (EpisodeReminder $n) => $n->toArray($zul)['type'] === 'episode_reminder'
            && $n->toArray($zul)['series_id'] === $episode->course_series_id);
        Notification::assertNotSentTo([$aina, $bala, $chong], EpisodeReminder::class);

        $this->postJson($url, ['audience' => 'not_finished'])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'A reminder went out at'));

        $this->travel(61)->minutes();
        $this->postJson($url, ['audience' => 'not_finished'])
            ->assertOk()
            ->assertJsonPath('data.sent', 3);
        Notification::assertSentTo([$bala, $chong], EpisodeReminder::class);
    }

    public function test_reminder_needs_a_released_episode_and_someone_to_remind(): void
    {
        $scheduled = $this->episode(['publish_at' => now()->addDay()]);
        $this->actingAsApi($this->lecturer)->postJson($this->tenantApi($this->tenant, "lecturer/watch/episodes/{$scheduled->id}/remind"))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This episode is not released yet.');

        $episode = $this->episode(['episode_number' => 2]);
        foreach ($this->students as $student) {
            $this->watched($episode, $student, 10);
        }
        $this->postJson($this->tenantApi($this->tenant, "lecturer/watch/episodes/{$episode->id}/remind"), ['audience' => 'not_started'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Everyone in this group has already started it.');
    }

    public function test_attendance_detail_shows_who_watched_the_weeks_episode(): void
    {
        $episode = $this->episode(['week_number' => 2]);
        [$aina, $bala] = $this->students;
        $this->watched($episode, $aina, 200, true);
        $this->watched($episode, $bala, 50);

        $session = $this->startAttendance($this->section, $this->lecturer, ['week_number' => 2]);
        $this->markAttendance($session, $aina);

        $response = $this->actingAsApi($this->lecturer)->getJson($this->tenantApi($this->tenant, "lecturer/attendance/{$session->id}"))
            ->assertOk()
            ->assertJsonPath('data.episode_watch.episode.title', 'Titis Leaves Home')
            ->assertJsonCount(4, 'data.episode_watch.students');

        $byUser = collect($response->json('data.episode_watch.students'))->keyBy('user_id');
        $this->assertSame('finished', $byUser[$aina->id]['status']);
        $this->assertSame(['watching', 25], [$byUser[$bala->id]['status'], $byUser[$bala->id]['watched_percent']]);
        $this->assertSame('not_started', $byUser[$this->students[3]->id]['status']);

        $noWeek = $this->startAttendance($this->createSection($this->course, ['code' => '03'], [$this->lecturer]), $this->lecturer, ['week_number' => 9]);
        $this->getJson($this->tenantApi($this->tenant, "lecturer/attendance/{$noWeek->id}"))
            ->assertOk()
            ->assertJsonPath('data.episode_watch', null);
    }
}
