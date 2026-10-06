<?php

namespace Tests\Feature\Api\V1\Student;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\EpisodeCaption;
use App\Models\EpisodeCheck;
use App\Models\EpisodeProgress;
use App\Models\EpisodeRewind;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Api\V1\ApiTestCase;

class StudentWatchInteractionsApiTest extends ApiTestCase
{
    use BuildsStudentFixtures;

    private function series(Course $course): CourseSeries
    {
        return CourseSeries::create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'title' => 'Titis: A Piping Story']);
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
            'video_path' => "episodes/ep{$number}.mp4",
            'duration_seconds' => 275,
        ], $attributes));
    }

    private function check(Episode $episode, int $at = 167): EpisodeCheck
    {
        $check = $episode->checks()->create(['at_seconds' => $at, 'prompt' => 'Which of these counts as piping?', 'explanation' => 'Supports are piping.']);
        foreach (['Building frame', 'Pipe hanger', 'Pump casing'] as $i => $label) {
            $check->options()->create(['label' => $label, 'is_correct' => $i === 1, 'sort_order' => $i]);
        }

        return $check->load('options');
    }

    public function test_episode_detail_carries_scenes_checks_and_captions_without_answers(): void
    {
        Storage::fake('local');
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $episode = $this->episode($this->series($course), 1);
        $episode->scenes()->createMany([
            ['code' => 'S09', 'title' => 'Quick Check', 'start_seconds' => 167, 'sort_order' => 1],
            ['code' => 'S01', 'title' => 'Meet Titis', 'start_seconds' => 0, 'sort_order' => 0],
        ]);
        $check = $this->check($episode);
        Storage::disk('local')->put('episodes/c/1-en.vtt', "WEBVTT\n\n00:00.000 --> 00:02.000\nMeet Titis\n");
        EpisodeCaption::create(['episode_id' => $episode->id, 'language' => 'en', 'disk' => 'local', 'path' => 'episodes/c/1-en.vtt']);

        $response = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/episodes/{$episode->id}"))
            ->assertOk()
            ->assertJsonPath('data.scenes.0.title', 'Meet Titis')
            ->assertJsonPath('data.scenes.1.start_seconds', 167)
            ->assertJsonPath('data.checks.0.id', $check->id)
            ->assertJsonPath('data.checks.0.options.1.label', 'Pipe hanger')
            ->assertJsonPath('data.checks.0.my_answer', null)
            ->assertJsonPath('data.captions.0.label', 'English')
            ->assertJsonPath('data.quick_checks.total', 1);

        $this->assertArrayNotHasKey('is_correct', $response->json('data.checks.0.options.0'));

        $this->app['auth']->forgetGuards();
        $caption = $this->withHeaders(['Authorization' => ''])->get($response->json('data.captions.0.url'));
        $caption->assertOk();
        $this->assertStringStartsWith('text/vtt', $caption->headers->get('Content-Type'));
    }

    public function test_answering_a_check_reveals_the_answer_and_counts_attempts(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $series = $this->series($course);
        $episode = $this->episode($series, 1);
        $check = $this->check($episode);
        [$frame, $hanger] = [$check->options[0], $check->options[1]];
        $url = $this->tenantApi($tenant, "student/watch/checks/{$check->id}/answer");

        $this->actingAsApi($student)->postJson($url, ['option_id' => $frame->id])
            ->assertOk()
            ->assertJsonPath('message', 'Answer saved.')
            ->assertJsonPath('data.is_correct', false)
            ->assertJsonPath('data.correct_option_id', $hanger->id)
            ->assertJsonPath('data.explanation', 'Supports are piping.')
            ->assertJsonPath('data.attempts', 1);

        $this->getJson($this->tenantApi($tenant, "student/watch/series/{$series->id}"))
            ->assertJsonPath('data.episodes.0.quick_checks', ['total' => 1, 'answered' => 1, 'correct' => 0]);

        $this->postJson($url, ['option_id' => $hanger->id])
            ->assertOk()
            ->assertJsonPath('data.is_correct', true)
            ->assertJsonPath('data.attempts', 2);

        $this->getJson($this->tenantApi($tenant, "student/watch/episodes/{$episode->id}"))
            ->assertJsonPath('data.checks.0.my_answer.option_id', $hanger->id)
            ->assertJsonPath('data.checks.0.my_answer.is_correct', true)
            ->assertJsonPath('data.quick_checks', ['total' => 1, 'answered' => 1, 'correct' => 1]);

        $answer = $check->answers()->sole();
        $this->assertFalse($answer->first_is_correct);
    }

    public function test_answer_must_be_an_option_of_that_check(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $series = $this->series($course);
        $check = $this->check($this->episode($series, 1));
        $other = $this->check($this->episode($series, 2));

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "student/watch/checks/{$check->id}/answer"), ['option_id' => $other->options[1]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('option_id');
    }

    public function test_checks_of_drafts_or_other_institutions_are_not_found(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $draftCheck = $this->check($this->episode($this->series($course), 1, ['status' => Episode::STATUS_DRAFT]));

        $otherTenant = $this->createTenant();
        $foreignCourse = $this->createCourse($otherTenant, $this->createMember($otherTenant, 'lecturer'));
        $foreignCheck = $this->check($this->episode($this->series($foreignCourse), 1));

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "student/watch/checks/{$draftCheck->id}/answer"), ['option_id' => $draftCheck->options[0]->id])
            ->assertNotFound();
        $this->postJson($this->tenantApi($tenant, "student/watch/checks/{$foreignCheck->id}/answer"), ['option_id' => $foreignCheck->options[0]->id])
            ->assertNotFound();
    }

    public function test_missed_weeks_row_uses_unexcused_absences(): void
    {
        [$tenant, $lecturer, $student, $course, $section] = $this->enrolledStudent();
        $series = $this->series($course);
        $week3 = $this->episode($series, 2, ['week_number' => 3]);
        $week4 = $this->episode($series, 3, ['week_number' => 4]);
        $this->episode($series, 4, ['week_number' => 5]);

        $absent = $this->createAttendanceSession($section, $lecturer, ['week_number' => 3, 'status' => 'ended', 'started_at' => now()->subWeek()]);
        $excused = $this->createAttendanceSession($section, $lecturer, ['week_number' => 4, 'status' => 'ended', 'started_at' => now()->subDays(2)]);
        $present = $this->createAttendanceSession($section, $lecturer, ['week_number' => 5, 'status' => 'ended', 'started_at' => now()->subDay()]);
        AttendanceRecord::create(['attendance_session_id' => $absent->id, 'user_id' => $student->id, 'status' => 'absent', 'method' => 'manual']);
        AttendanceRecord::create(['attendance_session_id' => $excused->id, 'user_id' => $student->id, 'status' => 'excused', 'method' => 'manual']);
        AttendanceRecord::create(['attendance_session_id' => $present->id, 'user_id' => $student->id, 'status' => 'present', 'method' => 'qr_scan']);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch'))
            ->assertOk()
            ->assertJsonCount(1, 'data.because_you_missed')
            ->assertJsonPath('data.because_you_missed.0.week_number', 3)
            ->assertJsonPath('data.because_you_missed.0.episode.id', $week3->id);

        EpisodeProgress::create(['episode_id' => $week3->id, 'user_id' => $student->id, 'position_seconds' => 275, 'completed_at' => now(), 'last_watched_at' => now()]);

        $this->getJson($this->tenantApi($tenant, 'student/watch'))
            ->assertJsonCount(0, 'data.because_you_missed');
        $this->assertNotNull($week4);
    }

    public function test_deadlines_drive_the_billboard_and_overdue_flag(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $series = $this->series($course);
        $late = $this->episode($series, 1, ['required_by' => now()->subDay()]);
        $soon = $this->episode($series, 2, ['required_by' => now()->addDays(2)]);
        $this->episode($series, 3, ['required_by' => now()->addDays(5)]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'student/watch'))
            ->assertOk()
            ->assertJsonPath('data.featured.id', $soon->id)
            ->assertJsonPath('data.series.0.episodes.0.id', $late->id)
            ->assertJsonPath('data.series.0.episodes.0.is_overdue', true)
            ->assertJsonPath('data.series.0.episodes.1.is_overdue', false);
    }

    public function test_progress_records_rewinds_and_keeps_newer_positions_over_offline_saves(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $episode = $this->episode($this->series($course), 1, ['allow_download' => false]);
        $url = $this->tenantApi($tenant, "student/watch/episodes/{$episode->id}/progress");

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/episodes/{$episode->id}"))
            ->assertJsonPath('data.can_download', false);

        $this->postJson($url, [
            'position_seconds' => 150,
            'rewinds' => [['from_seconds' => 131, 'to_seconds' => 104], ['from_seconds' => 50, 'to_seconds' => 48]],
        ])->assertOk();
        $this->assertSame([[131, 104]], EpisodeRewind::get(['from_seconds', 'to_seconds'])->map(fn ($r) => [$r->from_seconds, $r->to_seconds])->all());

        // A save queued offline yesterday arrives late: completion counts, the position stays.
        $this->postJson($url, ['position_seconds' => 274, 'watched_at' => now()->subDay()->toIso8601String()])
            ->assertOk()
            ->assertJsonPath('data.position_seconds', 150)
            ->assertJsonPath('data.completed', true);

        $this->postJson($url, ['rewinds' => array_fill(0, 51, ['from_seconds' => 20, 'to_seconds' => 1]), 'position_seconds' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rewinds');
    }

    public function test_youtube_episodes_play_from_youtube_and_cannot_be_downloaded(): void
    {
        [$tenant, , $student, $course] = $this->enrolledStudent();
        $series = $this->series($course);
        $episode = $this->episode($series, 1, [
            'source' => Episode::SOURCE_YOUTUBE,
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'video_path' => null,
            'allow_download' => true,
        ]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "student/watch/episodes/{$episode->id}"))
            ->assertOk()
            ->assertJsonPath('data.source', 'youtube')
            ->assertJsonPath('data.youtube', ['video_id' => 'dQw4w9WgXcQ', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])
            ->assertJsonPath('data.stream_url', null)
            ->assertJsonPath('data.stream_expires_at', null)
            ->assertJsonPath('data.mime_type', null)
            ->assertJsonPath('data.can_download', false)
            ->assertJsonPath('data.poster_url', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');

        $this->getJson($this->tenantApi($tenant, 'student/watch'))
            ->assertJsonPath('data.series.0.episodes.0.source', 'youtube');

        $this->app['auth']->forgetGuards();
        $signed = url(URL::temporarySignedRoute('api.v1.watch.episodes.stream', now()->addHour(), ['episode' => $episode->id], absolute: false));
        $this->withHeaders(['Authorization' => ''])->get($signed)->assertNotFound();
    }
}
