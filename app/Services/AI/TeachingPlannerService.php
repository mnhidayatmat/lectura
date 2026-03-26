<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Course;
use App\Models\TeachingPlan;
use App\Models\TeachingPlanWeek;

class TeachingPlannerService
{
    public function __construct(
        protected AiServiceManager $ai,
    ) {}

    public function generatePlan(Course $course, TeachingPlan $plan): void
    {
        $course->load(['topics', 'learningOutcomes']);

        foreach (range(1, $course->num_weeks) as $weekNum) {
            $topic = $course->topics->where('week_number', $weekNum)->first();
            $topicTitle = $topic?->title ?? "Week {$weekNum}";

            $prompt = $this->buildPrompt($course, $weekNum, $topicTitle);

            $result = $this->ai->complete($prompt, [
                'module' => 'teaching_plan',
                'course_id' => $course->id,
                'topic' => $topicTitle,
                'week' => $weekNum,
            ]);

            $data = json_decode($result['content'], true) ?? [];

            TeachingPlanWeek::updateOrCreate(
                ['teaching_plan_id' => $plan->id, 'week_number' => $weekNum],
                [
                    'topic' => $topicTitle,
                    'lesson_flow' => $data['lesson_flow'] ?? '',
                    'duration_minutes' => 70,
                    'active_learning' => $data['active_learning'] ?? [],
                    'online_alternatives' => $data['online_alternatives'] ?? [],
                    'formative_checks' => $data['formative_checks'] ?? [],
                    'time_allocation' => $data['time_allocation'] ?? [],
                    'assessment_notes' => $data['assessment_notes'] ?? '',
                    'ai_generated' => true,
                ]
            );
        }
    }

    protected function buildPrompt(Course $course, int $week, string $topic): string
    {
        $clos = $course->learningOutcomes->pluck('description', 'code')->map(fn($d, $c) => "{$c}: {$d}")->implode("\n");
        $mode = str_replace('_', ' ', $course->teaching_mode);

        return "You are an expert teaching plan designer. Generate a detailed weekly lesson plan for:

Course: {$course->code} - {$course->title}
Week: {$week}
Topic: {$topic}
Teaching Mode: {$mode}
Course Learning Outcomes:
{$clos}

Generate a structured lesson plan including:
1. Lesson flow with time allocations
2. Active learning activities suitable for university students
3. Online alternative activities
4. Formative assessment checks
5. Time allocation breakdown
6. Assessment alignment notes

Respond in JSON format.";
    }
}
