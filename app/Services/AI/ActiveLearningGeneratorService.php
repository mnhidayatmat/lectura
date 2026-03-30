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
            $studentSection = "\nNumber of Students: {$studentCount} (design group sizes appropriate for this class size)";
        }

        $lectureSection = '';
        if ($lectureNotesText) {
            $lectureSection = "

=== LECTURE CONTENT (ANALYZE DEEPLY) ===
The following is the actual lecture content for this session. You MUST:
1. Extract the key technical concepts, theories, formulas, definitions, and factual content
2. Use THIS SPECIFIC content to create activities — do NOT invent unrelated topics
3. Reference specific terms, examples, data, and concepts from this material
4. Every activity instruction must demonstrate deep understanding of this content

{$lectureNotesText}
=== END LECTURE CONTENT ===";
        }

        $focusInstructions = match ($contentFocus) {
            'case_study' => "
CONTENT FOCUS: CASE STUDIES
- Every core activity MUST include a realistic case study narrative (minimum 3-4 sentences describing a specific scenario, organisation, or situation) derived from the lecture content above
- The scenario must use real concepts, data, or principles from the lecture — not generic situations
- Students should analyse the case and answer specific questions about it
- Include clear deliverables: what exactly should the group produce?",
            'technical_problem' => "
CONTENT FOCUS: TECHNICAL PROBLEMS
- Every core activity MUST include a concrete technical problem with specific numbers, data, formulas, or code derived from the lecture content above
- Include the exact problem statement students must solve with all data provided
- Where appropriate, specify the expected form of the answer (calculation, diagram, code, written analysis)
- Problems must require applying concepts taught in the lecture, not just recall",
            'mixed' => "
CONTENT FOCUS: MIXED (Case Studies + Technical Problems)
- Include at least one case study activity (realistic scenario with narrative) AND at least one technical problem-solving activity
- Case studies must include a full narrative scenario with specific details from the lecture content
- Technical problems must include concrete data/numbers/specifications that students work through
- Both types must be derived from the lecture content — not generic exercises",
            default => "
CONTENT FOCUS: GENERAL
- Design activities that engage students with the lecture content
- Include practical application where possible",
        };

        return "You are an expert instructional designer. Your job is to create CONTENT-RICH active learning activities that are deeply tailored to the specific lecture material provided.

Course: {$course->code} - {$course->title}
Week: {$plan->week_number}
Topic: {$topic}
Duration: {$plan->duration_minutes} minutes
Teaching Mode: {$mode}{$studentSection}

Course Learning Outcomes:
{$clos}{$lectureSection}
{$focusInstructions}

ACTIVITY DESIGN REQUIREMENTS:
- Sequence: opener (recall/engage) → core activities (case study/problem-solving) → consolidation (reflect/synthesise)
- Include at least one individual and one collaborative activity
- Total durations must not exceed {$plan->duration_minutes} minutes
- The 'instructions' field is what students see — write COMPLETE student-facing content:
  * For case studies: the full scenario narrative, all questions, and expected deliverables
  * For technical problems: the complete problem statement with all data needed to solve it
  * For reflections: specific prompts tied to the lecture content
- Do NOT write vague instructions like \"discuss the topic\" or \"solve practice problems\"
- Every instruction must reference specific concepts from the lecture content

Respond ONLY with a JSON array:
[
  {
    \"title\": \"...\",
    \"type\": \"individual|pair|group|discussion|reflection|whole_class\",
    \"description\": \"Brief 1-2 sentence summary of the activity\",
    \"instructions\": \"FULL student-facing content. Include complete case narratives, problem statements with data, specific questions. Use numbered lists and line breaks for clarity.\",
    \"duration_minutes\": 15,
    \"clo_codes\": [\"CLO1\"],
    \"grouping_strategy\": \"random|manual|null\",
    \"max_group_size\": 4,
    \"response_type\": \"none|text|mcq|reflection\",
    \"response_mode\": \"individual|group\",
    \"content_meta\": {
      \"content_focus\": \"case_study|technical_problem|general\",
      \"difficulty\": \"introductory|intermediate|advanced\",
      \"expected_outcomes\": [\"What students should be able to do after this activity\"],
      \"key_concepts\": [\"concept1\", \"concept2\"]
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
