<?php

namespace Tests\Feature\Api\V1\Live;

use App\Models\ActiveLearningSession;
use App\Models\QuizParticipant;
use Tests\Feature\Api\V1\ApiTestCase;
use Tests\Feature\Api\V1\Live\Concerns\BuildsLiveFixtures;

class LiveHubApiTest extends ApiTestCase
{
    use BuildsLiveFixtures;

    public function test_requires_authentication(): void
    {
        $tenant = $this->createTenant();

        $this->getJson($this->tenantApi($tenant, 'live/hub'))->assertUnauthorized();
    }

    public function test_hub_lists_what_the_student_can_join_in_enrolled_sections(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();

        $session = $this->createLiveSession($section->course, $lecturer);
        $liveQuiz = $this->createQuiz($section, $lecturer);
        $offlineQuiz = $this->createOfflineQuiz($section, $lecturer);

        $otherSection = $this->createSection($this->createCourse($tenant, $lecturer), ['name' => 'Other', 'code' => '02']);
        $this->createQuiz($otherSection, $lecturer, ['title' => 'Not mine']);
        $this->createLiveSession($otherSection->course, $lecturer);

        $endedQuiz = $this->createQuiz($section, $lecturer, ['title' => 'Last week', 'status' => 'ended', 'ended_at' => now()]);
        QuizParticipant::create(['quiz_session_id' => $endedQuiz->id, 'user_id' => $student->id, 'display_name' => $student->name, 'total_score' => 4]);

        $this->actingAsApi($student)->getJson($this->tenantApi($tenant, 'live/hub'))
            ->assertOk()
            ->assertJsonCount(1, 'data.active_sessions')
            ->assertJsonPath('data.active_sessions.0.id', $session->id)
            ->assertJsonPath('data.active_sessions.0.title', 'Week 5: Derivatives')
            ->assertJsonPath('data.active_sessions.0.total_activities', 3)
            ->assertJsonPath('data.active_sessions.0.joined', false)
            ->assertJsonCount(1, 'data.live_quizzes')
            ->assertJsonPath('data.live_quizzes.0.id', $liveQuiz->id)
            ->assertJsonPath('data.live_quizzes.0.question_count', 2)
            ->assertJsonPath('data.live_quizzes.0.course.code', $section->course->code)
            ->assertJsonCount(1, 'data.offline_quizzes')
            ->assertJsonPath('data.offline_quizzes.0.id', $offlineQuiz->id)
            ->assertJsonPath('data.offline_quizzes.0.me.completed', false)
            ->assertJsonCount(1, 'data.recent_quizzes')
            ->assertJsonPath('data.recent_quizzes.0.id', $endedQuiz->id)
            ->assertJsonPath('data.recent_quizzes.0.me.score', 4)
            ->assertJsonCount(0, 'data.recent_sessions');
    }

    public function test_join_with_a_session_code_registers_the_participant(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $session = $this->createLiveSession($section->course, $lecturer);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'live/join'), ['code' => ' '.strtolower($session->join_code)])
            ->assertOk()
            ->assertJsonPath('data.type', 'session')
            ->assertJsonPath('data.id', $session->id);

        $this->assertDatabaseHas('active_learning_session_participants', ['session_id' => $session->id, 'user_id' => $student->id]);
    }

    public function test_join_with_a_quiz_code_registers_the_participant(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $quiz = $this->createQuiz($section, $lecturer);

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'live/join'), ['code' => $quiz->join_code])
            ->assertOk()
            ->assertJsonPath('data.type', 'quiz')
            ->assertJsonPath('data.id', $quiz->id)
            ->assertJsonPath('data.category', 'live');

        $this->assertDatabaseHas('quiz_participants', ['quiz_session_id' => $quiz->id, 'user_id' => $student->id]);
    }

    public function test_join_rejects_an_unknown_or_ended_code(): void
    {
        [$tenant, $lecturer, $student, $section] = $this->classroom();
        $ended = $this->createQuiz($section, $lecturer, ['status' => 'ended']);
        $completed = $this->createLiveSession($section->course, $lecturer, ActiveLearningSession::STATUS_COMPLETED);

        foreach (['ZZZZZZ', $ended->join_code, $completed->join_code] as $code) {
            $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'live/join'), ['code' => $code])
                ->assertStatus(422)
                ->assertJsonPath('errors.code.0', 'Invalid or expired join code.');
        }
    }

    public function test_join_requires_a_six_character_code(): void
    {
        [$tenant, , $student] = $this->classroom();

        $this->actingAsApi($student)->postJson($this->tenantApi($tenant, 'live/join'), ['code' => 'ABC'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_join_is_forbidden_when_not_enrolled(): void
    {
        [$tenant, $lecturer, , $section] = $this->classroom();
        $outsider = $this->createMember($tenant, 'student');
        $session = $this->createLiveSession($section->course, $lecturer);
        $quiz = $this->createQuiz($section, $lecturer);

        $this->actingAsApi($outsider)->postJson($this->tenantApi($tenant, 'live/join'), ['code' => $session->join_code])
            ->assertForbidden();

        $this->actingAsApi($outsider)->postJson($this->tenantApi($tenant, 'live/join'), ['code' => $quiz->join_code])
            ->assertForbidden();

        $this->assertDatabaseCount('quiz_participants', 0);
    }
}
