<?php

namespace Tests\Feature\Api\V1\Live\Concerns;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\ActiveLearningPollOption;
use App\Models\ActiveLearningSession;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuizSession;
use App\Models\QuizSessionQuestion;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

trait BuildsLiveFixtures
{
    /**
     * @return array{0: Tenant, 1: User, 2: User, 3: Section}
     */
    protected function classroom(): array
    {
        $tenant = $this->createTenant();
        $lecturer = $this->createMember($tenant, 'lecturer');
        $student = $this->createMember($tenant, 'student');
        $section = $this->createSection($this->createCourse($tenant, $lecturer), [], [$lecturer]);
        $this->enroll($section, $student);

        return [$tenant, $lecturer, $student, $section];
    }

    protected function createQuiz(Section $section, User $lecturer, array $attributes = [], int $questions = 2): QuizSession
    {
        $quiz = QuizSession::create(array_merge([
            'tenant_id' => $section->tenant_id,
            'section_id' => $section->id,
            'lecturer_id' => $lecturer->id,
            'title' => 'Week 5 Recap',
            'category' => 'live',
            'mode' => 'formative',
            'is_anonymous' => false,
            'status' => 'waiting',
        ], $attributes));

        $isOffline = $quiz->category === 'offline';

        for ($i = 0; $i < $questions; $i++) {
            $question = Question::create([
                'tenant_id' => $section->tenant_id,
                'created_by' => $lecturer->id,
                'question_type' => 'mcq',
                'text' => 'Question '.($i + 1),
                'explanation' => 'Explanation '.($i + 1),
                'time_limit_seconds' => 30,
                'points' => 2,
                'is_bank' => true,
            ]);

            foreach (['A' => false, 'B' => true, 'C' => false] as $label => $isCorrect) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'label' => $label,
                    'text' => 'Option '.$label,
                    'is_correct' => $isCorrect,
                    'sort_order' => ord($label) - 65,
                ]);
            }

            QuizSessionQuestion::create([
                'quiz_session_id' => $quiz->id,
                'question_id' => $question->id,
                'sort_order' => $i,
                'status' => $isOffline ? 'active' : 'pending',
                'opened_at' => $isOffline ? now() : null,
            ]);
        }

        return $quiz;
    }

    protected function createOfflineQuiz(Section $section, User $lecturer, array $attributes = []): QuizSession
    {
        return $this->createQuiz($section, $lecturer, array_merge([
            'title' => 'Chapter 3 Practice',
            'category' => 'offline',
            'status' => 'active',
            'available_from' => now()->subHour(),
            'available_until' => now()->addDay(),
            'started_at' => now()->subHour(),
        ], $attributes));
    }

    protected function openQuestion(QuizSession $quiz, int $index = 0): QuizSessionQuestion
    {
        $quiz->update(['status' => 'active', 'started_at' => $quiz->started_at ?? now()]);

        $sq = $quiz->sessionQuestions()->get()[$index];
        $sq->update(['status' => 'active', 'opened_at' => now()]);

        Cache::flush();

        return $sq;
    }

    protected function closeQuestion(QuizSessionQuestion $sq): void
    {
        $sq->update(['status' => 'closed', 'closed_at' => now()]);

        Cache::flush();
    }

    protected function correctOption(QuizSessionQuestion $sq): QuestionOption
    {
        return $sq->question->options()->where('is_correct', true)->firstOrFail();
    }

    protected function wrongOption(QuizSessionQuestion $sq): QuestionOption
    {
        return $sq->question->options()->where('is_correct', false)->firstOrFail();
    }

    protected function createLiveSession(Course $course, User $lecturer, string $status = ActiveLearningSession::STATUS_ACTIVE): ActiveLearningSession
    {
        $plan = ActiveLearningPlan::create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'week_number' => 5,
            'title' => 'Week 5: Derivatives',
            'status' => 'published',
            'created_by' => $lecturer->id,
            'published_at' => now(),
        ]);

        $poll = ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 0,
            'title' => 'Warm-up poll',
            'type' => 'individual',
            'instructions' => '<p>Which rule applies to <strong>sin(x²)</strong>?</p>',
            'solution' => 'Chain rule',
            'duration_minutes' => 5,
            'response_mode' => 'individual',
            'response_type' => 'mcq',
            'poll_config' => ['multi_select' => false, 'show_results' => true],
        ]);

        foreach (['Chain rule', 'Product rule', 'Quotient rule'] as $i => $label) {
            ActiveLearningPollOption::create([
                'activity_id' => $poll->id,
                'label' => $label,
                'sort_order' => $i,
                'is_correct' => $i === 0,
            ]);
        }

        ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 1,
            'title' => 'Think-pair-share',
            'type' => 'pair',
            'instructions' => 'Explain the chain rule to your partner.',
            'duration_minutes' => 10,
            'response_mode' => 'individual',
            'response_type' => 'text',
        ]);

        ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => 2,
            'title' => 'Exit ticket',
            'type' => 'reflection',
            'duration_minutes' => 3,
            'response_mode' => 'individual',
            'response_type' => 'reflection',
        ]);

        return ActiveLearningSession::create([
            'tenant_id' => $course->tenant_id,
            'plan_id' => $plan->id,
            'status' => $status,
            'current_activity_id' => $status === ActiveLearningSession::STATUS_ACTIVE ? $poll->id : null,
            'started_at' => $status === ActiveLearningSession::STATUS_NOT_STARTED ? null : now(),
            'ended_at' => $status === ActiveLearningSession::STATUS_COMPLETED ? now() : null,
            'created_by' => $lecturer->id,
        ]);
    }
}
