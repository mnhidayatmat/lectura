<?php

namespace Tests\Feature\Tenant;

use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Episode;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * An episode's linked live quiz: the lecturer runs it from the episode page and
 * students get a Join the quiz button in Watch while it runs.
 */
class EpisodeQuizTest extends ApiTestCase
{
    private Tenant $tenant;

    private User $lecturer;

    private User $student;

    private Course $course;

    private Section $section;

    private Episode $episode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant();
        $this->lecturer = $this->createMember($this->tenant, 'lecturer');
        $this->student = $this->createMember($this->tenant, 'student');
        $this->course = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG2663']);
        $this->section = $this->createSection($this->course, [], [$this->lecturer]);
        $this->enroll($this->section, $this->student);

        $series = CourseSeries::create(['tenant_id' => $this->tenant->id, 'course_id' => $this->course->id, 'title' => 'The Black River Chronicles']);
        $this->episode = Episode::create([
            'tenant_id' => $this->tenant->id,
            'course_series_id' => $series->id,
            'course_id' => $this->course->id,
            'episode_number' => 1,
            'title' => 'Born Under the Caprock',
            'status' => Episode::STATUS_PUBLISHED,
            'video_disk' => 'local',
            'video_path' => 'episodes/ep1.mp4',
            'duration_seconds' => 300,
        ]);
    }

    private function quiz(string $status = 'ended', ?Section $section = null): QuizSession
    {
        $quiz = QuizSession::create([
            'tenant_id' => $this->tenant->id,
            'section_id' => ($section ?? $this->section)->id,
            'lecturer_id' => $this->lecturer->id,
            'title' => 'EP01 · Born Under the Caprock',
            'category' => 'live',
            'mode' => 'formative',
            'status' => $status,
        ]);
        $question = Question::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->lecturer->id,
            'question_type' => 'mcq',
            'text' => 'By weight, which element makes up the largest share of crude oil?',
        ]);
        QuestionOption::create(['question_id' => $question->id, 'label' => 'A', 'text' => 'Carbon', 'is_correct' => true, 'sort_order' => 0]);
        QuestionOption::create(['question_id' => $question->id, 'label' => 'B', 'text' => 'Sulfur', 'is_correct' => false, 'sort_order' => 1]);
        QuizSessionQuestion::create(['quiz_session_id' => $quiz->id, 'question_id' => $question->id, 'sort_order' => 0]);

        return $quiz;
    }

    private function episodeUrl(string $path = ''): string
    {
        return "/{$this->tenant->slug}/materials/course/{$this->course->id}/episodes/{$this->episode->id}{$path}";
    }

    public function test_lecturer_links_a_course_quiz_and_unlinks_it(): void
    {
        $quiz = $this->quiz();

        $this->actingAs($this->lecturer)->put($this->episodeUrl('/quiz'), ['quiz_session_id' => $quiz->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Quiz linked to this episode.');
        $this->assertSame($quiz->id, $this->episode->fresh()->quiz_session_id);

        $this->actingAs($this->lecturer)->get($this->episodeUrl())
            ->assertOk()
            ->assertSee('Episode quiz')
            ->assertSee('EP01 · Born Under the Caprock')
            ->assertSee('Ready to start')
            ->assertSee('Start quiz');

        $this->actingAs($this->lecturer)->put($this->episodeUrl('/quiz'), ['quiz_session_id' => null])->assertRedirect();
        $this->assertNull($this->episode->fresh()->quiz_session_id);
    }

    public function test_a_quiz_from_another_course_cannot_be_linked(): void
    {
        $other = $this->createCourse($this->tenant, $this->lecturer, ['code' => 'BTG3333']);
        $quiz = $this->quiz(section: $this->createSection($other, [], [$this->lecturer]));

        $this->actingAs($this->lecturer)->put($this->episodeUrl('/quiz'), ['quiz_session_id' => $quiz->id])
            ->assertSessionHasErrors('quiz_session_id');
        $this->assertNull($this->episode->fresh()->quiz_session_id);
    }

    public function test_start_runs_a_fresh_copy_and_the_episode_follows_it(): void
    {
        $master = $this->quiz();
        $this->episode->update(['quiz_session_id' => $master->id]);

        $response = $this->actingAs($this->lecturer)->post($this->episodeUrl('/quiz/start'));

        $run = QuizSession::latest('id')->first();
        $this->assertNotSame($master->id, $run->id);
        $this->assertSame('waiting', $run->status);
        $this->assertSame(1, $run->sessionQuestions()->count());
        $this->assertSame($run->id, $this->episode->fresh()->quiz_session_id);
        $response->assertRedirect("/{$this->tenant->slug}/quizzes/{$run->id}/control");

        // While it runs, Open quiz goes back to the same run
        $this->actingAs($this->lecturer)->post($this->episodeUrl('/quiz/start'))
            ->assertRedirect("/{$this->tenant->slug}/quizzes/{$run->id}/control");
        $this->assertSame(2, QuizSession::count());
    }

    public function test_only_the_quiz_owner_can_start_it(): void
    {
        $owner = $this->createMember($this->tenant, 'lecturer');
        $quiz = $this->quiz();
        $quiz->update(['lecturer_id' => $owner->id]);
        $this->episode->update(['quiz_session_id' => $quiz->id]);

        $this->actingAs($this->lecturer)->post($this->episodeUrl('/quiz/start'))->assertForbidden();
    }

    public function test_students_see_join_the_quiz_only_while_it_runs(): void
    {
        $quiz = $this->quiz('waiting');
        $this->episode->update(['quiz_session_id' => $quiz->id]);
        $api = fn () => $this->actingAsApi($this->student)->getJson($this->tenantApi($this->tenant, "student/watch/episodes/{$this->episode->id}"));

        $api()->assertOk()->assertJsonPath('data.live_quiz', ['id' => $quiz->id, 'title' => 'EP01 · Born Under the Caprock', 'status' => 'waiting']);
        $this->actingAsApi($this->student)->getJson($this->tenantApi($this->tenant, "student/watch/series/{$this->episode->course_series_id}"))
            ->assertJsonPath('data.episodes.0.live_quiz.id', $quiz->id);
        $this->actingAs($this->student)->get("/{$this->tenant->slug}/watch/episodes/{$this->episode->id}")
            ->assertOk()
            ->assertSee('Join the quiz')
            ->assertSee("/quiz/{$quiz->id}/play", false);

        $quiz->update(['status' => 'ended']);
        $api()->assertJsonPath('data.live_quiz', null);
        $this->actingAs($this->student)->get("/{$this->tenant->slug}/watch/series/{$this->episode->course_series_id}")
            ->assertOk()
            ->assertDontSee('Join the quiz');
    }

    public function test_a_quiz_running_for_another_section_is_not_offered(): void
    {
        $quiz = $this->quiz('active', $this->createSection($this->course, ['name' => 'Section 02', 'code' => '02']));
        $this->episode->update(['quiz_session_id' => $quiz->id]);

        $this->actingAsApi($this->student)->getJson($this->tenantApi($this->tenant, "student/watch/episodes/{$this->episode->id}"))
            ->assertOk()
            ->assertJsonPath('data.live_quiz', null);
    }

    public function test_lecturer_api_reports_the_linked_quiz(): void
    {
        $url = $this->tenantApi($this->tenant, "lecturer/watch/episodes/{$this->episode->id}");
        $this->actingAsApi($this->lecturer)->getJson($url)->assertOk()->assertJsonPath('data.episode.quiz', null);

        $quiz = $this->quiz('waiting');
        $this->episode->update(['quiz_session_id' => $quiz->id]);

        $this->actingAsApi($this->lecturer)->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.episode.quiz.id', $quiz->id)
            ->assertJsonPath('data.episode.quiz.status', 'waiting')
            ->assertJsonPath('data.episode.quiz.join_code', $quiz->join_code)
            ->assertJsonPath('data.episode.quiz.questions_count', 1)
            ->assertJsonPath('data.episode.quiz.has_run', false)
            ->assertJsonPath('data.episode.quiz.web_url', url($this->episodeUrl()));
    }
}
