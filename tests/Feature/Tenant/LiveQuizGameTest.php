<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\QuizParticipant;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Live\Concerns\BuildsLiveFixtures;

/**
 * The Mentimeter-style live quiz end to end: lobby with nicknames, the board,
 * speed points, standings with rank movement, the podium and the saved results —
 * with students on the web player and in the mobile app at the same time.
 */
class LiveQuizGameTest extends ApiTestCase
{
    use BuildsLiveFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_full_game_from_lobby_to_podium_with_web_and_app_players(): void
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        [$tenant, $lecturer, $ali, $section] = $this->classroom();
        $mei = $this->createMember($tenant, 'student', ['name' => 'Mei Ling']);
        $raj = $this->createMember($tenant, 'student', ['name' => 'Raj Kumar']);
        $this->enroll($section, $mei);
        $this->enroll($section, $raj);
        $quiz = $this->createQuiz($section, $lecturer);
        $base = "/{$tenant->slug}";

        // Board in the lobby.
        $this->actingAs($lecturer)->get("{$base}/quizzes/{$quiz->id}/control")
            ->assertOk()
            ->assertSee('quizBoard', false)
            ->assertSee($quiz->join_code);

        // Ali and Mei play on the web, Raj in the app.
        $this->actingAs($ali)->get("{$base}/quiz/{$quiz->id}/play")->assertOk();
        $this->actingAs($mei)->get("{$base}/quiz/{$quiz->id}/play")->assertOk();
        $this->actingAsApi($raj)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"))->assertOk();

        $this->actingAs($ali)->postJson("{$base}/quiz/{$quiz->id}/nickname", ['nickname' => '  Pipe   Master '])
            ->assertOk()
            ->assertJsonPath('display_name', 'Pipe Master');
        $this->actingAsApi($raj)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/nickname"), ['nickname' => 'RajK'])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'RajK');

        $lobby = $this->actingAs($lecturer)->getJson("{$base}/quizzes/{$quiz->id}/state")
            ->assertOk()
            ->assertJsonPath('phase', 'lobby')
            ->assertJsonPath('participant_count', 3);
        $this->assertEqualsCanonicalizing(['Pipe Master', 'Mei Ling', 'RajK'], array_column($lobby->json('participants'), 'name'));

        $this->actingAs($ali)->getJson("{$base}/quiz/{$quiz->id}/student-state")
            ->assertJsonPath('phase', 'lobby')
            ->assertJsonPath('me.display_name', 'Pipe Master');

        // Start: question 1 opens with a 30 s clock.
        $this->actingAs($lecturer)->postJson("{$base}/quizzes/{$quiz->id}/start")
            ->assertOk()
            ->assertJsonPath('phase', 'answering')
            ->assertJsonPath('question.number', 1)
            ->assertJsonPath('question.remaining_seconds', 30)
            ->assertJsonMissingPath('question.options.0.is_correct');

        [$q1, $q2] = $quiz->sessionQuestions()->with('question.options')->get()->all();
        [$wrong1, $right1] = [$this->option($q1, false), $this->option($q1, true)];

        // Ali right after 3 s, Raj (app) right after 15 s, Mei wrong.
        Carbon::setTestNow('2026-10-20 09:00:03');
        $this->actingAs($ali)->postJson("{$base}/quiz/{$quiz->id}/respond", ['session_question_id' => $q1->id, 'selected_option_id' => $right1])
            ->assertOk()->assertJsonPath('message', 'Answer submitted.');
        $this->actingAs($ali)->postJson("{$base}/quiz/{$quiz->id}/respond", ['session_question_id' => $q1->id, 'selected_option_id' => $wrong1])
            ->assertOk()->assertJsonPath('message', 'Already answered.');

        Carbon::setTestNow('2026-10-20 09:00:15');
        $this->actingAsApi($raj)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), ['session_question_id' => $q1->id, 'selected_option_id' => $right1])
            ->assertOk();
        $this->actingAs($mei)->postJson("{$base}/quiz/{$quiz->id}/respond", ['session_question_id' => $q1->id, 'selected_option_id' => $wrong1])
            ->assertOk();

        $this->actingAs($ali)->getJson("{$base}/quiz/{$quiz->id}/student-state")
            ->assertJsonPath('phase', 'answering')
            ->assertJsonPath('me.answered', true)
            ->assertJsonPath('question.remaining_seconds', 15)
            ->assertJsonMissingPath('question.correct_option_id');

        $this->actingAs($lecturer)->getJson("{$base}/quizzes/{$quiz->id}/state")
            ->assertJsonPath('answered_count', 3)
            ->assertJsonPath('leaderboard', null);

        // Moving on is refused while the question is open.
        $this->actingAs($lecturer)->postJson("{$base}/quizzes/{$quiz->id}/next")->assertJsonPath('phase', 'answering');

        // Close: the board shows the answer spread and the standings.
        $reveal = $this->actingAs($lecturer)->postJson("{$base}/quizzes/{$quiz->id}/close")
            ->assertOk()
            ->assertJsonPath('phase', 'reveal')
            ->assertJsonPath('is_last_question', false)
            ->assertJsonPath('question.explanation', 'Explanation 1');
        $counts = collect($reveal->json('question.options'))->pluck('count', 'id');
        $this->assertSame(2, $counts[$right1]);
        $this->assertSame(1, $counts[$wrong1]);

        $board = $reveal->json('leaderboard');
        $this->assertSame(['Pipe Master', 'RajK', 'Mei Ling'], array_column($board, 'name'));
        $this->assertSame([950, 750, 0], array_column($board, 'points'));
        $this->assertSame([950, 750, 0], array_column($board, 'gained'));
        $this->assertSame([1, 2, 3], array_column($board, 'rank'));
        $this->assertSame([null, null, null], array_column($board, 'previous_rank'));

        $this->actingAsApi($raj)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertJsonPath('data.phase', 'reveal')
            ->assertJsonPath('data.me.is_correct', true)
            ->assertJsonPath('data.me.points', 750)
            ->assertJsonPath('data.me.points_gained', 750)
            ->assertJsonPath('data.me.score', 2)
            ->assertJsonPath('data.me.rank', 2)
            ->assertJsonPath('data.leaderboard.1.is_me', true)
            ->assertJsonPath('data.question.correct_option_id', $right1);

        // Question 2: Mei and Raj fast, Ali wrong — Raj takes the lead, Mei overtakes Ali.
        Carbon::setTestNow('2026-10-20 09:01:00');
        $this->actingAs($lecturer)->postJson("{$base}/quizzes/{$quiz->id}/next")
            ->assertJsonPath('phase', 'answering')
            ->assertJsonPath('question.number', 2);
        $right2 = $this->option($q2, true);

        Carbon::setTestNow('2026-10-20 09:01:01');
        $this->actingAsApi($raj)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), ['session_question_id' => $q2->id, 'selected_option_id' => $right2])->assertOk();
        $this->actingAs($mei)->postJson("{$base}/quiz/{$quiz->id}/respond", ['session_question_id' => $q2->id, 'selected_option_id' => $right2])->assertOk();
        $this->actingAs($ali)->postJson("{$base}/quiz/{$quiz->id}/respond", ['session_question_id' => $q2->id, 'selected_option_id' => $this->option($q2, false)])->assertOk();

        $board = $this->actingAs($lecturer)->postJson("{$base}/quizzes/{$quiz->id}/close")
            ->assertJsonPath('is_last_question', true)
            ->json('leaderboard');
        $this->assertSame(['RajK', 'Mei Ling', 'Pipe Master'], array_column($board, 'name'));
        $this->assertSame([1733, 983, 950], array_column($board, 'points'));
        $this->assertSame([983, 983, 0], array_column($board, 'gained'));
        $this->assertSame([1, 2, 3], array_column($board, 'rank'));
        $this->assertSame([2, 3, 1], array_column($board, 'previous_rank'));

        // Final standings for the podium, then save.
        $this->actingAs($lecturer)->postJson("{$base}/quizzes/{$quiz->id}/next")
            ->assertJsonPath('phase', 'finished')
            ->assertJsonPath('leaderboard.0.name', 'RajK');

        $this->actingAs($mei)->getJson("{$base}/quiz/{$quiz->id}/student-state")
            ->assertJsonPath('phase', 'finished')
            ->assertJsonPath('me.rank', 2)
            ->assertJsonPath('me.points', 983);

        $this->actingAs($lecturer)->postJson("{$base}/quizzes/{$quiz->id}/end")
            ->assertOk()
            ->assertJsonPath('redirect', route('tenant.quizzes.results', [$tenant->slug, $quiz->id]));

        $quiz->refresh();
        $this->assertSame('ended', $quiz->status);
        $raj = QuizParticipant::where('quiz_session_id', $quiz->id)->where('user_id', $raj->id)->first();
        $this->assertSame(1733, $raj->game_score);
        $this->assertSame(4.0, (float) $raj->total_score);

        $this->actingAs($lecturer)->get("{$base}/quizzes/{$quiz->id}/results")
            ->assertOk()
            ->assertSeeInOrder(['Raj Kumar', 'as “RajK”', '1,733'], false);

        $this->actingAsApi($raj->user)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/result"))
            ->assertOk()
            ->assertJsonPath('data.rank', 1)
            ->assertJsonPath('data.points', 1733);
    }

    public function test_an_answer_after_the_timer_and_grace_is_refused(): void
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $other = $this->createMember($tenant, 'student');
        $this->enroll($section, $other);
        $quiz = $this->runningQuiz($section, $lecturer, [$student, $other]);
        $q1 = $quiz->sessionQuestions()->first();

        Carbon::setTestNow('2026-10-20 09:00:32');
        $this->actingAs($student)->postJson("/{$tenant->slug}/quiz/{$quiz->id}/respond", [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertOk();

        Carbon::setTestNow('2026-10-20 09:00:34');
        $this->actingAs($other)->postJson("/{$tenant->slug}/quiz/{$quiz->id}/respond", [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertStatus(422)->assertJsonPath('message', 'Time is up for this question.');

        $this->actingAsApi($other)->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q1, true),
        ])->assertStatus(422)->assertJsonPath('message', 'Time is up for this question.');

        // An answer inside the grace window still scores, at the slowest rate.
        $this->assertSame(500, QuizParticipant::where('user_id', $student->id)->value('game_score'));
    }

    public function test_an_option_from_another_question_is_refused(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->runningQuiz($section, $lecturer, [$student]);
        [$q1, $q2] = $quiz->sessionQuestions()->get()->all();

        $this->actingAs($student)->postJson("/{$tenant->slug}/quiz/{$quiz->id}/respond", [
            'session_question_id' => $q1->id, 'selected_option_id' => $this->option($q2, true),
        ])->assertStatus(422);

        $this->assertSame(0, QuizParticipant::where('user_id', $student->id)->value('game_score'));
    }

    public function test_nicknames_are_cleaned_and_validated(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $url = "/{$tenant->slug}/quiz/{$quiz->id}/nickname";

        $this->actingAs($student)->postJson($url, ['nickname' => 'Too early'])->assertForbidden();
        $this->actingAs($student)->get("/{$tenant->slug}/quiz/{$quiz->id}/play")->assertOk();

        $this->actingAs($student)->postJson($url, ['nickname' => "   \t "])->assertStatus(422);
        $this->actingAs($student)->postJson($url, ['nickname' => str_repeat('x', 31)])->assertStatus(422);
        $this->actingAs($student)->postJson($url, ['nickname' => "Ali\u{0007} the  Great"])
            ->assertOk()->assertJsonPath('display_name', 'Ali the Great');

        $anonymous = $this->createQuiz($section, $lecturer, ['is_anonymous' => true]);
        $this->actingAs($student)->get("/{$tenant->slug}/quiz/{$anonymous->id}/play")->assertOk();
        $this->actingAs($student)->postJson("/{$tenant->slug}/quiz/{$anonymous->id}/nickname", ['nickname' => 'Me'])->assertStatus(422);
        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, "live/quizzes/{$anonymous->id}/nickname"), ['nickname' => 'Me'])
            ->assertStatus(422);
    }

    public function test_only_the_owner_can_drive_the_board(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);

        foreach (['start', 'close', 'next', 'end'] as $action) {
            $this->actingAs($student)->postJson("/{$tenant->slug}/quizzes/{$quiz->id}/{$action}")->assertForbidden();
        }
        $this->assertSame('waiting', $quiz->fresh()->status);
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
