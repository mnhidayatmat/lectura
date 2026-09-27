<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuizFolder;
use App\Models\QuizParticipant;
use App\Models\QuizResponse;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * The lecturer quiz list, control page and results page, and the rules
 * around who may change a quiz.
 */
class QuizResultsTest extends ApiTestCase
{
    public function test_results_summarise_a_run_question_by_question_and_rank_the_students(): void
    {
        [$tenant, $lecturer, $course, $section] = $this->setUpCourse();
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended', started: true);
        [$q1, $q2] = $quiz->sessionQuestions()->with('question.options')->get()->all();

        $ali = User::factory()->create(['name' => 'Ali Hassan']);
        $mei = User::factory()->create(['name' => 'Mei Ling']);
        $pAli = $this->participant($quiz, $ali, 2);
        $pMei = $this->participant($quiz, $mei, 1);

        // Q1: both right. Q2: Ali right, Mei picks the wrong option.
        $this->answer($q1, $pAli, correct: true, ms: 4000);
        $this->answer($q1, $pMei, correct: true, ms: 6000);
        $this->answer($q2, $pAli, correct: true, ms: 5000);
        $this->answer($q2, $pMei, correct: false, ms: 9000);

        $plan = ActiveLearningPlan::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'title' => 'S1 – Piping Fundamentals',
            'created_by' => $lecturer->id,
        ]);
        ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'title' => 'Quiz time',
            'type' => 'whole_class',
            'quiz_session_id' => $quiz->id,
        ]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/quizzes/{$quiz->id}/results")
            ->assertOk()
            // 3 of 4 points on average = 75 %, every question answered
            ->assertSeeInOrder(['Participants', '2', 'Average score', '75%', 'Completion', '100%', 'Hardest question', 'Q2', '50% correct'])
            ->assertSee('Worth re-teaching')
            ->assertSee('Most common wrong answer')
            ->assertSeeInOrder(['Ali Hassan', 'Mei Ling'])
            ->assertSee('5 s') // Ali's average response time
            ->assertSee('Run again')
            ->assertSee("/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}", false)
            ->assertSee('S1 – Piping Fundamentals');
    }

    public function test_tied_scores_share_a_rank(): void
    {
        [$tenant, $lecturer, , $section] = $this->setUpCourse();
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended', started: true);

        foreach (['Ana', 'Ben', 'Cai'] as $i => $name) {
            $this->participant($quiz, User::factory()->create(['name' => $name]), $i < 2 ? 2 : 1);
        }

        $response = $this->actingAs($lecturer)->get("/{$tenant->slug}/quizzes/{$quiz->id}/results")->assertOk();

        // Two gold medals for the tie, then rank 3 (bronze), no silver.
        $this->assertSame(2, substr_count($response->getContent(), '🥇'));
        $this->assertSame(0, substr_count($response->getContent(), '🥈'));
        $this->assertSame(1, substr_count($response->getContent(), '🥉'));
    }

    public function test_a_never_run_quiz_shows_an_empty_state_with_a_start_button(): void
    {
        [$tenant, $lecturer, , $section] = $this->setUpCourse();
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended');

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/quizzes/{$quiz->id}/results")
            ->assertOk()
            ->assertSee('This quiz has not been run yet.')
            ->assertSee('Start quiz')
            ->assertDontSee('Leaderboard')
            ->assertSee('No answers');
    }

    public function test_an_anonymous_quiz_never_shows_real_names(): void
    {
        [$tenant, $lecturer, , $section] = $this->setUpCourse();
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended', started: true, anonymous: true);
        $this->participant($quiz, User::factory()->create(['name' => 'Zainab Real']), 1, displayName: 'Blue Fox');

        $html = $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/quizzes/{$quiz->id}/results")
            ->assertOk()
            ->assertSee('Blue Fox')
            ->assertDontSee('Zainab')
            ->getContent();

        // The avatar initial comes from the display name, not the real name.
        $this->assertMatchesRegularExpression('/>\s*B\s*</', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*Z\s*</', $html);
    }

    public function test_co_lecturer_sees_results_without_owner_actions_and_a_stranger_is_refused(): void
    {
        [$tenant, $lecturer, $course, $section] = $this->setUpCourse();
        $coLecturer = $this->createMember($tenant, 'lecturer');
        $section->lecturers()->attach([$lecturer->id, $coLecturer->id]);
        $stranger = $this->createMember($tenant, 'lecturer');
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended', started: true);

        $this->actingAs($coLecturer)
            ->get("/{$tenant->slug}/quizzes/{$quiz->id}/results")
            ->assertOk()
            ->assertDontSee('Run again');

        $this->actingAs($coLecturer)
            ->get("/{$tenant->slug}/quizzes/course/{$course->id}")
            ->assertOk()
            ->assertSee($quiz->title)
            ->assertDontSee("/quizzes/{$quiz->id}/edit", false);

        $this->actingAs($stranger)
            ->get("/{$tenant->slug}/quizzes/{$quiz->id}/results")
            ->assertForbidden();
    }

    public function test_quiz_list_reads_a_never_run_quiz_as_not_run_yet_with_start(): void
    {
        [$tenant, $lecturer, $course, $section] = $this->setUpCourse();
        $master = $this->createQuiz($section, $lecturer, status: 'ended', title: 'Master quiz');
        $finished = $this->createQuiz($section, $lecturer, status: 'ended', started: true, title: 'Finished run');

        $html = $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/quizzes/course/{$course->id}")
            ->assertOk()
            ->getContent();

        $masterRow = substr($html, strpos($html, 'Master quiz'), 4000);
        $this->assertStringContainsString('Not run yet', $masterRow);
        $this->assertStringContainsString("/quizzes/{$master->id}/replay", $masterRow);
        $this->assertStringNotContainsString("/quizzes/{$master->id}/results", $masterRow);

        $finishedRow = substr($html, strpos($html, 'Finished run'), 4000);
        $this->assertStringContainsString('Ended', $finishedRow);
        $this->assertStringContainsString("/quizzes/{$finished->id}/results", $finishedRow);
    }

    public function test_control_page_offers_start_for_a_master_and_hides_its_join_code(): void
    {
        [$tenant, $lecturer, , $section] = $this->setUpCourse();
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended');

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/quizzes/{$quiz->id}/control")
            ->assertOk()
            ->assertSee('Not run yet')
            ->assertSee('Start quiz')
            ->assertDontSee('Join Code:');
    }

    public function test_starting_only_works_from_the_lobby(): void
    {
        [$tenant, $lecturer, , $section] = $this->setUpCourse();
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended');

        $this->actingAs($lecturer)->post("/{$tenant->slug}/quizzes/{$quiz->id}/start")->assertRedirect();

        $this->assertSame('ended', $quiz->fresh()->status);
        $this->assertNull($quiz->fresh()->started_at);
    }

    public function test_a_student_cannot_poll_the_lecturer_quiz_state(): void
    {
        [$tenant, $lecturer, , $section] = $this->setUpCourse();
        $student = $this->createMember($tenant, 'student');
        $quiz = $this->createQuiz($section, $lecturer, status: 'active', started: true);

        $this->actingAs($student)->getJson("/{$tenant->slug}/quizzes/{$quiz->id}/state")->assertForbidden();
        $this->actingAs($lecturer)->getJson("/{$tenant->slug}/quizzes/{$quiz->id}/state")->assertOk();
    }

    public function test_a_quiz_cannot_be_put_in_a_section_or_folder_the_lecturer_does_not_own(): void
    {
        [$tenant, $lecturer, , $section] = $this->setUpCourse();
        $other = $this->createMember($tenant, 'lecturer');
        $otherSection = $this->createSection($this->createCourse($tenant, $other));
        $otherFolder = QuizFolder::create(['tenant_id' => $tenant->id, 'lecturer_id' => $other->id, 'name' => 'Theirs', 'color' => 'indigo']);
        $quiz = $this->createQuiz($section, $lecturer, status: 'ended');

        $payload = [
            'title' => 'New quiz',
            'category' => 'live',
            'mode' => 'formative',
            'questions' => [['text' => 'Q?', 'type' => 'true_false', 'options' => [['text' => 'True', 'is_correct' => true], ['text' => 'False']]]],
        ];

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/quizzes", $payload + ['section_id' => $otherSection->id])
            ->assertSessionHasErrors('section_id');

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/quizzes", $payload + ['section_id' => $section->id, 'quiz_folder_id' => $otherFolder->id])
            ->assertSessionHasErrors('quiz_folder_id');

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/quizzes/{$quiz->id}/move", ['quiz_folder_id' => $otherFolder->id])
            ->assertSessionHasErrors('quiz_folder_id');
        $this->assertNull($quiz->fresh()->quiz_folder_id);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/quizzes", $payload + ['section_id' => $section->id])
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Course, 3: Section}
     */
    private function setUpCourse(): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $section = $this->createSection($course);

        return [$tenant, $lecturer, $course, $section];
    }

    private function createQuiz(Section $section, User $lecturer, string $status = 'waiting', bool $started = false, bool $anonymous = false, string $title = 'S1 quiz'): QuizSession
    {
        $quiz = QuizSession::create([
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'lecturer_id' => $lecturer->id,
            'title' => $title,
            'category' => 'live',
            'mode' => 'formative',
            'is_anonymous' => $anonymous,
            'status' => $status,
            'started_at' => $started ? now()->subHour() : null,
            'ended_at' => $status === 'ended' && $started ? now() : null,
        ]);

        foreach (['Which code covers process piping?', 'Weld joint factor E for seamless pipe?'] as $i => $text) {
            $question = Question::create([
                'tenant_id' => $section->tenant_id,
                'created_by' => $lecturer->id,
                'question_type' => 'mcq',
                'text' => $text,
                'explanation' => 'Because the code says so.',
                'points' => 1,
            ]);
            foreach (['Right answer', 'Wrong answer'] as $j => $option) {
                QuestionOption::create(['question_id' => $question->id, 'label' => chr(65 + $j), 'text' => $option, 'is_correct' => $j === 0, 'sort_order' => $j]);
            }
            QuizSessionQuestion::create(['quiz_session_id' => $quiz->id, 'question_id' => $question->id, 'sort_order' => $i, 'status' => $started ? 'closed' : 'pending']);
        }

        return $quiz;
    }

    private function participant(QuizSession $quiz, User $user, float $score, ?string $displayName = null): QuizParticipant
    {
        return QuizParticipant::create([
            'quiz_session_id' => $quiz->id,
            'user_id' => $user->id,
            'display_name' => $displayName ?? $user->name,
            'total_score' => $score,
            'joined_at' => now()->subHour(),
        ]);
    }

    private function answer(QuizSessionQuestion $sq, QuizParticipant $participant, bool $correct, int $ms): void
    {
        $option = $sq->question->options->firstWhere('is_correct', $correct);

        QuizResponse::create([
            'quiz_session_question_id' => $sq->id,
            'quiz_participant_id' => $participant->id,
            'selected_option_id' => $option->id,
            'is_correct' => $correct,
            'points_earned' => $correct ? 1 : 0,
            'response_time_ms' => $ms,
        ]);
    }
}
