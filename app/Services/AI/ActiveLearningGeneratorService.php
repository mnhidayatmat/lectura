<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\CourseLearningOutcome;

class ActiveLearningGeneratorService
{
    public function __construct(
        protected AiServiceManager $ai,
    ) {}

    public function generate(ActiveLearningPlan $plan, ?string $lectureNotesText = null, int $studentCount = 0): void
    {
        $plan->load(['course.topics', 'course.learningOutcomes', 'topic']);

        $prompt = $this->buildPrompt($plan, $lectureNotesText, $studentCount);

        $result = $this->ai->complete($prompt, [
            'module' => 'active_learning',
            'course_id' => $plan->course_id,
        ]);

        $activities = $this->parseAiResponse($result['content'] ?? '', $plan);

        // Clear existing AI-generated activities
        $plan->activities()->where('ai_generated', true)->delete();

        $maxOrder = $plan->activities()->max('sort_order') ?? -1;

        foreach ($activities as $index => $activityData) {
            ActiveLearningActivity::create([
                'active_learning_plan_id' => $plan->id,
                'sort_order' => $maxOrder + 1 + $index,
                'title' => $activityData['title'],
                'type' => $activityData['type'],
                'description' => $activityData['description'] ?? null,
                'instructions' => $activityData['instructions'] ?? null,
                'duration_minutes' => $activityData['duration_minutes'] ?? null,
                'clo_ids' => $activityData['clo_ids'] ?? null,
                'grouping_strategy' => $activityData['grouping_strategy'] ?? null,
                'max_group_size' => $activityData['max_group_size'] ?? null,
                'response_type' => $activityData['response_type'] ?? 'none',
                'response_mode' => $activityData['response_mode'] ?? 'individual',
                'ai_generated' => true,
            ]);
        }
    }

    public function buildPrompt(ActiveLearningPlan $plan, ?string $lectureNotesText, int $studentCount = 0): string
    {
        $course = $plan->course;
        $topic = $plan->topic?->title ?? "Week {$plan->week_number}";
        $mode = str_replace('_', ' ', $course->teaching_mode ?? 'face to face');

        $clos = $course->learningOutcomes
            ->pluck('description', 'code')
            ->map(fn ($d, $c) => "{$c}: {$d}")
            ->implode("\n");

        $lectureSection = '';
        if ($lectureNotesText) {
            $lectureSection = "\n\nLecture Content / Notes (use this to contextualise activities):\n{$lectureNotesText}";
        }

        $studentSection = '';
        if ($studentCount > 0) {
            $studentSection = "\nNumber of Students: {$studentCount} (design group sizes and activity types appropriate for this class size)";
        }

        return "You are an expert instructional designer specialising in active learning pedagogy.

Course: {$course->code} - {$course->title}
Week: {$plan->week_number}
Topic: {$topic}
Duration: {$plan->duration_minutes} minutes
Teaching Mode: {$mode}{$studentSection}

Course Learning Outcomes relevant to this week:
{$clos}{$lectureSection}

Design a set of active learning activities for this session.
Requirements:
- Activities must be sequenced (opener → core → consolidation)
- Include at least one individual and one collaborative activity
- Each activity must have a clear student-facing instruction
- Activities must address the stated CLOs
- Total activity durations should not exceed {$plan->duration_minutes} minutes
- Each activity should specify a response_type so students can respond on their mobile devices during class
- For group activities, set appropriate group sizes based on the number of students

Respond ONLY with a JSON array:
[
  {
    \"title\": \"...\",
    \"type\": \"individual|pair|group|discussion|reflection|whole_class\",
    \"description\": \"...\",
    \"instructions\": \"...\",
    \"duration_minutes\": 15,
    \"clo_codes\": [\"CLO1\", \"CLO2\"],
    \"grouping_strategy\": \"random|manual|null\",
    \"max_group_size\": 4,
    \"response_type\": \"none|text|mcq|reflection\",
    \"response_mode\": \"individual|group\"
  }
]";
    }

    public function parseAiResponse(string $content, ActiveLearningPlan $plan): array
    {
        // Extract JSON from response
        $content = trim($content);

        // Try to find JSON array in the response
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $content = $matches[0];
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        $validTypes = ActiveLearningActivity::TYPES;
        $validResponseTypes = ActiveLearningActivity::RESPONSE_TYPES;
        $validResponseModes = ActiveLearningActivity::RESPONSE_MODES;
        $cloMap = $plan->course->learningOutcomes->pluck('id', 'code')->all();

        $activities = [];
        foreach ($decoded as $item) {
            if (! isset($item['title'], $item['type'])) {
                continue;
            }

            $type = in_array($item['type'], $validTypes) ? $item['type'] : 'individual';

            // Map CLO codes to IDs
            $cloIds = null;
            if (! empty($item['clo_codes']) && is_array($item['clo_codes'])) {
                $cloIds = array_values(array_filter(
                    array_map(fn ($code) => $cloMap[$code] ?? null, $item['clo_codes'])
                ));
            }

            $responseType = in_array($item['response_type'] ?? '', $validResponseTypes)
                ? $item['response_type']
                : 'none';
            $responseMode = in_array($item['response_mode'] ?? '', $validResponseModes)
                ? $item['response_mode']
                : 'individual';

            $activities[] = [
                'title' => $item['title'],
                'type' => $type,
                'description' => $item['description'] ?? null,
                'instructions' => $item['instructions'] ?? null,
                'duration_minutes' => isset($item['duration_minutes']) ? (int) $item['duration_minutes'] : null,
                'clo_ids' => $cloIds ?: null,
                'grouping_strategy' => $item['grouping_strategy'] ?? null,
                'max_group_size' => isset($item['max_group_size']) ? (int) $item['max_group_size'] : null,
                'response_type' => $responseType,
                'response_mode' => $responseMode,
            ];
        }

        return $activities;
    }
}
