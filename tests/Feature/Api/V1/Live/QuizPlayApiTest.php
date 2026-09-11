<?php

namespace Tests\Feature\Api\V1\Live;

use App\Models\QuizParticipant;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Live\Concerns\BuildsLiveFixtures;

class QuizPlayApiTest extends ApiTestCase
{
    use BuildsLiveFixtures;

    public function test_show_registers_the_student_for_a_live_quiz(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"))
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.playable', true)
            ->assertJsonPath('data.question_count', 2)
            ->assertJsonPath('data.total_points', 4)
            ->assertJsonPath('data.me.joined', true)
            ->assertJsonPath('data.questions', []);

        $this->assertDatabaseHas('quiz_participants', ['quiz_session_id' => $quiz->id, 'user_id' => $student->id]);
    }

    public function test_student_not_enrolled_in_the_section_is_forbidden(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $outsider = $this->createMember($tenant, 'student');
        $quiz = $this->createQuiz($section, $lecturer);

        $this->actingAsApi($outsider)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"))
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not enrolled in this section.');
    }

    public function test_quiz_from_another_institution_is_not_found(): void
    {
        [, $lecturer, , $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);

        $otherTenant = $this->createTenant();
        $otherStudent = $this->createMember($otherTenant, 'student');

        $this->actingAsApi($otherStudent)->getJson($this->tenantApi($otherTenant, "live/quizzes/{$quiz->id}/state"))
            ->assertNotFound();
    }

    public function test_state_in_the_lobby(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.phase', 'lobby')
            ->assertJsonPath('data.participant_count', 1)
            ->assertJsonPath('data.question', null)
            ->assertJsonPath('data.me.joined', true);
    }

    public function test_state_while_answering_never_reveals_the_correct_answer(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));
        $sq = $this->openQuestion($quiz);

        $response = $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertOk()
            ->assertJsonPath('data.phase', 'answering')
            ->assertJsonPath('data.question.session_question_id', $sq->id)
            ->assertJsonPath('data.question.number', 1)
            ->assertJsonPath('data.question.total', 2)
            ->assertJsonCount(3, 'data.question.options')
            ->assertJsonPath('data.me.answered', false)
            ->assertJsonMissingPath('data.question.correct_option_id')
            ->assertJsonMissingPath('data.question.explanation')
            ->assertJsonMissingPath('data.question.options.0.is_correct')
            ->assertJsonPath('data.leaderboard', null);

        $this->assertStringNotContainsString('is_correct', json_encode($response->json('data.question')));
        $this->assertGreaterThan(0, $response->json('data.question.remaining_seconds'));
    }

    public function test_student_answers_once_and_sees_the_result_after_close(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));
        $sq = $this->openQuestion($quiz);
        $correct = $this->correctOption($sq);

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $sq->id,
            'selected_option_id' => $correct->id,
            'response_time_ms' => 4200,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Answer submitted.')
            ->assertJsonPath('data.selected_option_id', $correct->id)
            ->assertJsonMissingPath('data.is_correct');

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $sq->id,
            'selected_option_id' => $this->wrongOption($sq)->id,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Already answered.')
            ->assertJsonPath('data.selected_option_id', $correct->id);

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertJsonPath('data.me.answered', true)
            ->assertJsonPath('data.me.selected_option_id', $correct->id)
            ->assertJsonPath('data.me.is_correct', null);

        $this->closeQuestion($sq);

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertOk()
            ->assertJsonPath('data.phase', 'reveal')
            ->assertJsonPath('data.question.correct_option_id', $correct->id)
            ->assertJsonPath('data.question.explanation', 'Explanation 1')
            ->assertJsonPath('data.me.is_correct', true)
            ->assertJsonPath('data.me.points_earned', 2)
            ->assertJsonPath('data.me.score', 2)
            ->assertJsonPath('data.me.rank', 1)
            ->assertJsonPath('data.leaderboard.0.score', 2)
            ->assertJsonPath('data.leaderboard.0.is_me', true);

        $this->assertSame('2.00', QuizParticipant::where('user_id', $student->id)->value('total_score'));
    }

    public function test_respond_validation_and_authorization(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $sq = $this->openQuestion($quiz);
        $other = $quiz->sessionQuestions()->get()[1];
        $url = $this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond");

        $this->actingAsApi($student)->postJson($url, ['session_question_id' => $sq->id, 'selected_option_id' => $this->correctOption($sq)->id])
            ->assertForbidden()
            ->assertJsonPath('message', 'You have not joined this quiz.');

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));

        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors('session_question_id');

        $this->postJson($url, ['session_question_id' => $sq->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('selected_option_id');

        $this->postJson($url, ['session_question_id' => $sq->id, 'selected_option_id' => $this->correctOption($other)->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.selected_option_id.0', 'The selected option is invalid.');

        $this->postJson($url, ['session_question_id' => $other->id, 'selected_option_id' => $this->correctOption($other)->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Question is not active.');

        $this->assertDatabaseCount('quiz_responses', 0);
    }

    public function test_offline_quiz_lists_questions_without_answers(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createOfflineQuiz($section, $lecturer);

        $response = $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"))
            ->assertOk()
            ->assertJsonPath('data.category', 'offline')
            ->assertJsonPath('data.playable', true)
            ->assertJsonPath('data.can_view_result', false)
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonCount(3, 'data.questions.0.options');

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('Explanation', $response->getContent());

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This quiz is not a live quiz.');
    }

    public function test_offline_quiz_submission_is_scored_once_and_result_reveals_answers(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createOfflineQuiz($section, $lecturer);
        [$first, $second] = $quiz->sessionQuestions()->get()->all();

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/result"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Submit the quiz to see your result.');

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/submit-offline"), [
            'answers' => [
                $first->id => $this->correctOption($first)->id,
                $second->id => $this->wrongOption($second)->id,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Quiz submitted successfully!')
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.max_score', 4)
            ->assertJsonPath('data.correct_count', 1)
            ->assertJsonPath('data.accuracy', 50)
            ->assertJsonPath('data.questions.0.is_correct', true)
            ->assertJsonPath('data.questions.1.is_correct', false)
            ->assertJsonPath('data.questions.1.correct_option_id', $this->correctOption($second)->id);

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/submit-offline"), [
            'answers' => [$first->id => $this->correctOption($first)->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already submitted this quiz.');

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"))
            ->assertJsonPath('data.me.completed', true)
            ->assertJsonPath('data.can_view_result', true)
            ->assertJsonPath('data.questions', []);

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/result"))
            ->assertOk()
            ->assertJsonPath('data.rank', 1)
            ->assertJsonPath('data.questions.0.explanation', 'Explanation 1');
    }

    public function test_offline_submission_requires_an_open_quiz_and_answers(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createOfflineQuiz($section, $lecturer);
        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/submit-offline"), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('answers');

        $quiz->update(['available_until' => now()->subMinute()]);

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/submit-offline"), ['answers' => [1 => 1]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This quiz is not currently available.');
    }

    public function test_live_result_is_available_only_after_the_quiz_ends(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));
        $sq = $this->openQuestion($quiz);

        $this->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $sq->id,
            'selected_option_id' => $this->wrongOption($sq)->id,
        ])->assertOk();

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/result"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Results are available when the quiz ends.');

        $this->closeQuestion($sq);
        $quiz->update(['status' => 'ended', 'ended_at' => now()]);

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/state"))
            ->assertJsonPath('data.phase', 'finished')
            ->assertJsonPath('data.me.rank', 1);

        $this->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/result"))
            ->assertOk()
            ->assertJsonPath('data.score', 0)
            ->assertJsonPath('data.questions.0.answered', true)
            ->assertJsonPath('data.questions.1.answered', false)
            ->assertJsonPath('data.questions.0.correct_option_id', $this->correctOption($sq)->id);
    }
}
