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

    public function generate(ActiveLearningPlan $plan, ?string $lectureNotesText = null, int $studentCount = 0, string $contentFocus = 'mixed'): void
    {
        $plan->load(['course.topics', 'course.learningOutcomes', 'topic']);

        $prompt = $this->buildPrompt($plan, $lectureNotesText, $studentCount, $contentFocus);

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
                'content_meta' => $activityData['content_meta'] ?? null,
                'ai_generated' => true,
            ]);
        }
    }

    public function buildPrompt(ActiveLearningPlan $plan, ?string $lectureNotesText, int $studentCount = 0, string $contentFocus = 'mixed'): string
    {
        $course = $plan->course;
        $topic = $plan->topic?->title ?? "Week {$plan->week_number}";
        $mode = str_replace('_', ' ', $course->teaching_mode ?? 'face to face');

        $clos = $course->learningOutcomes
            ->pluck('description', 'code')
            ->map(fn ($d, $c) => "{$c}: {$d}")
            ->implode("\n");

        $studentSection = '';
        if ($studentCount > 0) {
            $studentSection = "\nNumber of Students: {$studentCount}";
        }

        $focusInstructions = match ($contentFocus) {
            'case_study' => 'CASE STUDIES — every core activity must be a realistic scenario that forces students to apply the extracted concepts to diagnose, analyse, or solve a situation',
            'technical_problem' => 'TECHNICAL PROBLEMS — every core activity must be a concrete problem with specific data/numbers/code that students must solve using the extracted concepts',
            'mixed' => 'MIXED — include at least one case study AND one technical problem, both derived from the lecture concepts',
            default => 'GENERAL — design activities that engage students with the lecture content',
        };

        $lectureSection = '';
        if ($lectureNotesText) {
            $lectureSection = "

=== LECTURE SLIDE CONTENT ===
{$lectureNotesText}
=== END LECTURE SLIDE CONTENT ===";
        }

        return "You are an expert instructional designer. You will receive lecture slide content below. Your task has TWO phases.

Course: {$course->code} - {$course->title}
Week: {$plan->week_number}, Topic: {$topic}
Duration: {$plan->duration_minutes} minutes, Teaching Mode: {$mode}{$studentSection}
CLOs:
{$clos}{$lectureSection}

────────────────────────────────────────
PHASE 1 — CONTENT EXTRACTION (do this internally, do not output)
────────────────────────────────────────
Before generating activities, you MUST first internally identify:
- Every specific concept, definition, theory, model, or framework in the lecture
- Every formula, equation, algorithm, or method shown
- Every example, diagram description, or data set mentioned
- Every technical term introduced or explained
- The logical flow: what builds on what

────────────────────────────────────────
PHASE 2 — ACTIVITY GENERATION (output this)
────────────────────────────────────────
Content Focus: {$focusInstructions}

CRITICAL RULES:
1. Students have ALREADY studied these slides in advance. Activities must test and deepen their understanding — NOT re-teach the content.
2. Every activity instruction MUST use SPECIFIC terms, concepts, formulas, examples, or data FROM the lecture slides. Quote or reference them directly.
3. Case studies: invent a realistic scenario (company, project, situation) where the EXACT concepts from the slides apply. The scenario must be detailed enough that students cannot solve it without having studied the lecture. Include specific questions that map to specific slide concepts.
4. Technical problems: use the EXACT formulas, methods, or techniques from the slides. Provide concrete data and ask students to apply the method step-by-step. If the lecture shows a formula, the problem must require using that formula with new numbers.
5. NEVER write generic instructions like \"discuss the topic\", \"apply the concepts\", or \"solve practice problems\". Every instruction must name the specific concept being applied.
6. The opener should test whether students actually read the slides (e.g., ask them to explain a specific definition or reproduce a specific diagram from memory).
7. The consolidation should ask students to connect multiple concepts from the lecture, not just recall one.

Activity sequence: opener (5-10 min) → core activities → consolidation (5-10 min)
Total duration must not exceed {$plan->duration_minutes} minutes.

Respond ONLY with a JSON array (no other text):
[
  {
    \"title\": \"...\",
    \"type\": \"individual|pair|group|discussion|reflection|whole_class\",
    \"description\": \"1-2 sentence summary\",
    \"instructions\": \"FULL student-facing content with specific references to lecture concepts. For case studies: complete scenario narrative + numbered questions. For problems: complete problem statement with all data.\",
    \"duration_minutes\": 15,
    \"clo_codes\": [\"CLO1\"],
    \"grouping_strategy\": \"random|manual|null\",
    \"max_group_size\": 4,
    \"response_type\": \"none|text|mcq|reflection\",
    \"response_mode\": \"individual|group\",
    \"content_meta\": {
      \"content_focus\": \"case_study|technical_problem|general\",
      \"difficulty\": \"introductory|intermediate|advanced\",
      \"expected_outcomes\": [\"Specific outcome tied to lecture concept\"],
      \"key_concepts\": [\"exact_term_from_slides\"]
    }
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

            $contentMeta = null;
            if (! empty($item['content_meta']) && is_array($item['content_meta'])) {
                $validFocus = ActiveLearningActivity::CONTENT_FOCUS_TYPES;
                $contentMeta = [
                    'content_focus' => in_array($item['content_meta']['content_focus'] ?? '', $validFocus)
                        ? $item['content_meta']['content_focus'] : 'general',
                    'difficulty' => $item['content_meta']['difficulty'] ?? null,
                    'expected_outcomes' => is_array($item['content_meta']['expected_outcomes'] ?? null)
                        ? $item['content_meta']['expected_outcomes'] : [],
                    'key_concepts' => is_array($item['content_meta']['key_concepts'] ?? null)
                        ? $item['content_meta']['key_concepts'] : [],
                ];
            }

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
                'content_meta' => $contentMeta,
            ];
        }

        return $activities;
    }
}
