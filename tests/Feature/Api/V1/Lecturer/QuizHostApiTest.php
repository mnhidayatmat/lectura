<?php

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\QuizSession;
use Tests\Feature\Api\V1\Live\Concerns\BuildsLiveFixtures;

class QuizHostApiTest extends LecturerApiTestCase
{
    use BuildsLiveFixtures;

    public function test_index_lists_live_quizzes_running_ones_first(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $ended = $this->createQuiz($section, $lecturer, ['title' => 'Old run', 'status' => 'ended']);
        $waiting = $this->createQuiz($section, $lecturer, ['title' => 'Week 6']);
        $this->createOfflineQuiz($section, $lecturer);

        $this->actingAsApi($lecturer, 'lecturer')->getJson($this->tenantApi($tenant, 'lecturer/quizzes'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $waiting->id)
            ->assertJsonPath('data.0.status', 'waiting')
            ->assertJsonPath('data.0.question_count', 2)
            ->assertJsonPath('data.0.can_run', true)
            ->assertJsonPath('data.0.is_owner', true)
            ->assertJsonPath('data.0.course.code', $section->course->code)
            ->assertJsonPath('data.0.section.id', $section->id)
            ->assertJsonPath('data.1.id', $ended->id)
            ->assertJsonPath('data.1.can_run', false)
            ->assertJsonPath('data.1.never_run', true);
    }

    public function test_index_filters_by_course_and_hides_other_lecturers_courses(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $this->createQuiz($section, $lecturer);

        $other = $this->createMember($tenant, 'lecturer');
        $otherSection = $this->createSection($this->createCourse($tenant, $other), [], [$other]);
        $this->createQuiz($otherSection, $other);

        $this->actingAsApi($lecturer, 'lecturer')->getJson($this->tenantApi($tenant, 'lecturer/quizzes'))
            ->assertJsonCount(1, 'data');

        $this->getJson($this->tenantApi($tenant, 'lecturer/quizzes?course_id='.$otherSection->course_id))
            ->assertJsonCount(0, 'data');
    }

    public function test_students_cannot_use_the_host_api(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'lecturer/quizzes'))
            ->assertForbidden();
        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/start"))
            ->assertForbidden();
    }

    public function test_full_run_from_lobby_to_end(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}"));

        $this->actingAsApi($lecturer, 'lecturer')
            ->getJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}"))
            ->assertOk()
            ->assertJsonPath('data.board.phase', 'lobby')
            ->assertJsonPath('data.board.participant_count', 1)
            ->assertJsonPath('data.join_code', $quiz->join_code);

        $first = $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/start"))
            ->assertOk()
            ->assertJsonPath('message', 'Quiz started.')
            ->assertJsonPath('data.board.phase', 'answering')
            ->assertJsonPath('data.board.question.number', 1)
            ->assertJsonMissingPath('data.board.question.options.0.is_correct')
            ->json('data.board.question.session_question_id');

        $sq = $quiz->sessionQuestions()->find($first);
        $this->actingAsApi($student, 'student')->postJson($this->tenantApi($tenant, "live/quizzes/{$quiz->id}/respond"), [
            'session_question_id' => $sq->id,
            'selected_option_id' => $this->correctOption($sq)->id,
        ])->assertOk();

        $this->actingAsApi($lecturer, 'lecturer')
            ->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/next"))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Close the open question first.');

        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/close"), ['session_question_id' => $first])
            ->assertOk()
            ->assertJsonPath('data.board.phase', 'reveal')
            ->assertJsonPath('data.board.answered_count', 1)
            ->assertJsonPath('data.board.question.options.1.is_correct', true)
            ->assertJsonPath('data.board.question.options.1.count', 1)
            ->assertJsonPath('data.board.leaderboard.0.rank', 1);

        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/next"))
            ->assertOk()
            ->assertJsonPath('data.board.phase', 'answering')
            ->assertJsonPath('data.board.question.number', 2)
            ->assertJsonPath('data.board.is_last_question', true);

        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/close"))->assertOk();

        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/next"))
            ->assertOk()
            ->assertJsonPath('message', 'Final standings.')
            ->assertJsonPath('data.board.phase', 'finished');

        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/end"))
            ->assertOk()
            ->assertJsonPath('data.status', 'ended')
            ->assertJsonPath('data.board.phase', 'ended')
            ->assertJsonPath('data.can_run', false);

        $this->assertDatabaseHas('quiz_participants', ['quiz_session_id' => $quiz->id, 'user_id' => $student->id, 'total_score' => 2]);

        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/start"))
            ->assertStatus(422);
    }

    public function test_a_late_close_never_closes_the_next_question(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $first = $this->openQuestion($quiz, 0);
        $this->closeQuestion($first);
        $second = $this->openQuestion($quiz, 1);

        $this->actingAsApi($lecturer, 'lecturer')
            ->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/close"), ['session_question_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('data.board.phase', 'answering')
            ->assertJsonPath('data.board.question.session_question_id', $second->id);
    }

    public function test_co_lecturer_can_watch_but_not_run(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $colleague = $this->createMember($tenant, 'lecturer');
        $section->lecturers()->attach($colleague->id);

        $this->actingAsApi($colleague, 'lecturer')
            ->getJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}"))
            ->assertOk()
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.can_run', false);

        $this->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/start"))
            ->assertForbidden()
            ->assertJsonPath('message', 'Only the lecturer who created this quiz can run it.');
    }

    public function test_outsider_lecturer_cannot_see_the_quiz(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);
        $outsider = $this->createMember($tenant, 'lecturer');

        $this->actingAsApi($outsider, 'lecturer')
            ->getJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}"))
            ->assertForbidden();
    }

    public function test_offline_quizzes_are_refused(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $quiz = $this->createOfflineQuiz($section, $lecturer);

        $this->actingAsApi($lecturer, 'lecturer')
            ->getJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}"))
            ->assertStatus(422);
    }

    public function test_replay_creates_a_fresh_run_in_the_lobby(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer, ['status' => 'ended']);

        $id = $this->actingAsApi($lecturer, 'lecturer')
            ->postJson($this->tenantApi($tenant, "lecturer/quizzes/{$quiz->id}/replay"))
            ->assertCreated()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.board.phase', 'lobby')
            ->assertJsonPath('data.question_count', 2)
            ->assertJsonPath('data.can_run', true)
            ->json('data.id');

        $this->assertNotSame($quiz->id, $id);
        $this->assertSame('ended', QuizSession::find($quiz->id)->status);
    }

    public function test_quiz_from_another_institution_is_not_found(): void
    {
        [, $lecturer, , $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);

        $otherTenant = $this->createTenant();
        $otherLecturer = $this->createMember($otherTenant, 'lecturer');

        $this->actingAsApi($otherLecturer, 'lecturer')
            ->getJson($this->tenantApi($otherTenant, "lecturer/quizzes/{$quiz->id}"))
            ->assertNotFound();
    }
}
