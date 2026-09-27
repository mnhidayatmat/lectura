<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\User;
use Tests\Feature\Api\V1\ApiTestCase;

/**
 * An activity can carry a quiz slot that opens one of the course's quizzes.
 */
class ActiveLearningQuizSlotTest extends ApiTestCase
{
    public function test_plan_pages_show_the_quiz_slot_with_a_button_to_the_quiz(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $quiz = $this->createQuiz($course, $lecturer, questions: 3);
        [$plan, $activity] = $this->createPlanWithActivity($course, $lecturer, ['quiz_session_id' => $quiz->id]);

        $base = "/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}";

        foreach ([$base, "{$base}/edit"] as $url) {
            $this->actingAs($lecturer)
                ->get($url)
                ->assertOk()
                ->assertSee('Quiz time')
                ->assertSee('S1 quiz')
                ->assertSee('3 questions')
                ->assertSee($quiz->join_code)
                ->assertSee('Open quiz')
                ->assertSee("{$base}/activities/{$activity->id}/quiz", false);
        }
    }

    public function test_opening_a_quiz_that_is_not_finished_goes_straight_to_its_control_page(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $quiz = $this->createQuiz($course, $lecturer);
        [$plan, $activity] = $this->createPlanWithActivity($course, $lecturer, ['quiz_session_id' => $quiz->id]);

        $this->actingAs($lecturer)
            ->post("/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}/activities/{$activity->id}/quiz")
            ->assertRedirect("/{$tenant->slug}/quizzes/{$quiz->id}/control");

        $this->assertSame(1, QuizSession::count());
    }

    public function test_starting_a_master_quiz_runs_a_fresh_copy_and_the_slot_follows_it(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $master = $this->createQuiz($course, $lecturer, questions: 2, status: 'ended');
        [$plan, $activity] = $this->createPlanWithActivity($course, $lecturer, ['quiz_session_id' => $master->id]);
        $base = "/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}";

        $this->actingAs($lecturer)
            ->get($base)
            ->assertSee('Start quiz')
            ->assertSee('Ready to start')
            ->assertDontSee($master->join_code)
            ->assertDontSee('Last results');

        $response = $this->actingAs($lecturer)->post("{$base}/activities/{$activity->id}/quiz");

        $run = QuizSession::whereKeyNot($master->id)->sole();
        $response->assertRedirect("/{$tenant->slug}/quizzes/{$run->id}/control");
        $this->assertSame('waiting', $run->status);
        $this->assertSame(2, $run->sessionQuestions()->count());
        $this->assertSame('ended', $master->fresh()->status);
        $this->assertSame($run->id, $activity->fresh()->quiz_session_id);
    }

    public function test_a_finished_run_offers_its_results_and_a_new_start(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $quiz = $this->createQuiz($course, $lecturer, status: 'ended');
        $quiz->update(['started_at' => now()->subHour(), 'ended_at' => now()]);
        [$plan] = $this->createPlanWithActivity($course, $lecturer, ['quiz_session_id' => $quiz->id]);

        $this->actingAs($lecturer)
            ->get("/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}")
            ->assertOk()
            ->assertSee('Start quiz')
            ->assertSee('Last results')
            ->assertSee("/{$tenant->slug}/quizzes/{$quiz->id}/results", false);
    }

    public function test_another_lecturer_cannot_start_the_quiz(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->createMember($tenant, 'lecturer');
        $stranger = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $owner);
        $quiz = $this->createQuiz($course, $owner, status: 'ended');
        [$plan, $activity] = $this->createPlanWithActivity($course, $owner, ['quiz_session_id' => $quiz->id]);

        $this->actingAs($stranger)
            ->post("/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}/activities/{$activity->id}/quiz")
            ->assertForbidden();

        $this->assertSame(1, QuizSession::count());
    }

    public function test_quiz_replay_still_copies_the_questions_and_options(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $quiz = $this->createQuiz($course, $lecturer, status: 'ended');
        $question = $quiz->sessionQuestions()->first()->question;
        $question->options()->createMany([
            ['label' => 'A', 'text' => 'True', 'is_correct' => true, 'sort_order' => 0],
            ['label' => 'B', 'text' => 'False', 'is_correct' => false, 'sort_order' => 1],
        ]);

        $response = $this->actingAs($lecturer)->post("/{$tenant->slug}/quizzes/{$quiz->id}/replay");

        $copy = QuizSession::whereKeyNot($quiz->id)->sole();
        $response->assertRedirect("/{$tenant->slug}/quizzes/{$copy->id}/control");
        $copiedQuestion = $copy->sessionQuestions()->first()->question;
        $this->assertNotSame($question->id, $copiedQuestion->id);
        $this->assertSame(['True', 'False'], $copiedQuestion->options()->orderBy('sort_order')->pluck('text')->all());
        $this->assertTrue((bool) $copiedQuestion->options()->where('label', 'A')->value('is_correct'));
    }

    public function test_lecturer_can_link_a_quiz_from_the_same_course_only(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $otherCourse = $this->createCourse($tenant, $lecturer);
        $quiz = $this->createQuiz($course, $lecturer);
        $foreignQuiz = $this->createQuiz($otherCourse, $lecturer);
        [$plan, $activity] = $this->createPlanWithActivity($course, $lecturer);

        $url = "/{$tenant->slug}/courses/{$course->id}/active-learning/{$plan->id}/activities/{$activity->id}";
        $payload = ['title' => 'Quiz time', 'type' => 'whole_class'];

        $this->actingAs($lecturer)
            ->put($url, $payload + ['quiz_session_id' => $foreignQuiz->id])
            ->assertSessionHasErrors('quiz_session_id');
        $this->assertNull($activity->fresh()->quiz_session_id);

        $this->actingAs($lecturer)
            ->put($url, $payload + ['quiz_session_id' => $quiz->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($quiz->id, $activity->fresh()->quiz_session_id);

        $this->actingAs($lecturer)
            ->put($url, $payload + ['quiz_session_id' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($activity->fresh()->quiz_session_id);
    }

    public function test_deleting_the_quiz_empties_the_slot(): void
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $course = $this->createCourse($tenant, $lecturer);
        $quiz = $this->createQuiz($course, $lecturer);
        [, $activity] = $this->createPlanWithActivity($course, $lecturer, ['quiz_session_id' => $quiz->id]);

        $quiz->delete();

        $this->assertNull($activity->fresh()->quiz_session_id);
    }

    private function createQuiz(Course $course, User $lecturer, int $questions = 1, string $status = 'waiting'): QuizSession
    {
        $section = $this->createSection($course);

        $quiz = QuizSession::create([
            'tenant_id' => $course->tenant_id,
            'section_id' => $section->id,
            'lecturer_id' => $lecturer->id,
            'title' => 'S1 quiz',
            'category' => 'live',
            'mode' => 'formative',
            'status' => $status,
        ]);

        for ($i = 0; $i < $questions; $i++) {
            $question = Question::create([
                'tenant_id' => $course->tenant_id,
                'created_by' => $lecturer->id,
                'question_type' => 'true_false',
                'text' => "Question {$i}",
            ]);
            QuizSessionQuestion::create([
                'quiz_session_id' => $quiz->id,
                'question_id' => $question->id,
                'sort_order' => $i,
            ]);
        }

        return $quiz;
    }

    /**
     * @return array{0: ActiveLearningPlan, 1: ActiveLearningActivity}
     */
    private function createPlanWithActivity(Course $course, User $lecturer, array $attributes = []): array
    {
        $plan = ActiveLearningPlan::create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'title' => 'S1',
            'created_by' => $lecturer->id,
        ]);

        $activity = ActiveLearningActivity::create(array_merge([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 0,
            'title' => 'Quiz time',
            'type' => 'whole_class',
            'duration_minutes' => 7,
        ], $attributes));

        return [$plan, $activity];
    }
}
