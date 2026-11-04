<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\QuizDeparture;
use App\Models\QuizParticipant;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Live\Concerns\BuildsLiveFixtures;

/**
 * Leaving a live quiz (to another app, tab or window) while a question is open and
 * unanswered forfeits that question, on the web player and in the app alike.
 */
class LiveQuizDepartureTest extends ApiTestCase
{
    use BuildsLiveFixtures;

    private const LEFT = 'You left the quiz during this question, so it can no longer be answered.';

    public function test_leaving_the_app_forfeits_the_open_question_everywhere(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $other = $this->createMember($tenant, 'student');
        $this->enroll($section, $other);
        $quiz = $this->runningQuiz($section, $lecturer, [$student, $other]);
        $q1 = $quiz->sessionQuestions()->first();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/away"), ['session_question_id' => $q1->id])
            ->assertOk()
            ->assertJsonPath('data.forfeited', true);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertStatus(422)->assertJsonPath('message', self::LEFT);

        $this->actingAs($student)->postJson("/{$tenant->slug}/quiz/{$quiz->id}/respond", [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertStatus(422)->assertJsonPath('message', self::LEFT);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertJsonPath('data.me.left', true)
            ->assertJsonPath('data.me.left_count', 1)
            ->assertJsonPath('data.me.answered', false);

        // Somebody else is untouched.
        $this->actingAsApi($other)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertJsonPath('data.me.left', false);
        $this->actingAsApi($other)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertOk();

        $this->actingAs($lecturer)->getJson("/{$tenant->slug}/quizzes/{$quiz->id}/state")
            ->assertJsonPath('answered_count', 1)
            ->assertJsonPath('left_count', 1);

        $this->actingAs($lecturer)->postJson("/{$tenant->slug}/quizzes/{$quiz->id}/close")->assertOk();

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertJsonPath('data.phase', 'reveal')
            ->assertJsonPath('data.me.left', true)
            ->assertJsonPath('data.me.is_correct', null);

        $this->assertSame(0, QuizParticipant::where('user_id', $student->id)->value('game_score'));

        $this->actingAs($lecturer)->get("/{$tenant->slug}/quizzes/{$quiz->id}/results")
            ->assertOk()
            ->assertSee('Left the quiz on 1 question');
    }

    public function test_leaving_the_web_player_forfeits_the_question(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->runningQuiz($section, $lecturer, [$student]);
        $q1 = $quiz->sessionQuestions()->first();

        $this->actingAs($student)->postJson("/{$tenant->slug}/quiz/{$quiz->id}/away", ['session_question_id' => $q1->id])
            ->assertOk()
            ->assertJsonPath('forfeited', true);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertStatus(422)->assertJsonPath('message', self::LEFT);

        $this->assertDatabaseHas('quiz_departures', ['quiz_session_question_id' => $q1->id, 'client' => 'web']);
    }

    public function test_leaving_after_answering_or_outside_a_question_costs_nothing(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->runningQuiz($section, $lecturer, [$student]);
        [$q1, $q2] = $quiz->sessionQuestions()->get()->all();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertOk();

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/away"), ['session_question_id' => $q1->id])
            ->assertOk()
            ->assertJsonPath('data.forfeited', false);

        // The next question has not been opened yet.
        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/away"), ['session_question_id' => $q2->id])
            ->assertOk()
            ->assertJsonPath('data.forfeited', false);

        $this->assertSame(0, QuizDeparture::count());
        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertJsonPath('data.me.answered', true)
            ->assertJsonPath('data.me.left_count', 0);
    }

    public function test_a_repeated_or_late_report_is_recorded_once(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->runningQuiz($section, $lecturer, [$student]);
        $q1 = $quiz->sessionQuestions()->first();

        $this->actingAs($lecturer)->postJson("/{$tenant->slug}/quizzes/{$quiz->id}/close")->assertOk();

        // Reported on return, after the question had already closed: still counts for the lecturer.
        foreach (range(1, 2) as $attempt) {
            $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/away"), ['session_question_id' => $q1->id])
                ->assertOk()
                ->assertJsonPath('data.forfeited', true);
        }

        $this->assertSame(1, QuizDeparture::count());
    }

    public function test_a_question_of_another_quiz_is_not_found(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->runningQuiz($section, $lecturer, [$student]);
        $otherQuiz = $this->createQuiz($section, $lecturer);
        $foreign = $otherQuiz->sessionQuestions()->first();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/away"), ['session_question_id' => $foreign->id])
            ->assertNotFound();
        $this->actingAs($student)->postJson("/{$tenant->slug}/quiz/{$quiz->id}/away", ['session_question_id' => $foreign->id])
            ->assertNotFound();
    }

    /**
     * @param  list<User>  $players
     */
    private function runningQuiz($section, User $lecturer, array $players): QuizSession
    {
        $quiz = $this->createQuiz($section, $lecturer);
        foreach ($players as $player) {
            $this->actingAs($player)->get("/{$section->tenant->slug}/quiz/{$quiz->id}/play")->assertOk();
        }
        $this->actingAs($lecturer)->postJson("/{$section->tenant->slug}/quizzes/{$quiz->id}/start")->assertOk();

        return $quiz;
    }

    private function option(QuizSessionQuestion $question, bool $correct): int
    {
        return $question->question()->first()->options()->where('is_correct', $correct)->value('id');
    }
}
