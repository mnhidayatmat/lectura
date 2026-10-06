<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AiProviderInterface;

class MockProvider implements AiProviderInterface
{
    public function complete(string $prompt, array $options = []): array
    {
        // Simulate AI response delay
        usleep(200000); // 200ms

        return [
            'content' => $this->generateMockResponse($prompt, $options),
            'input_tokens' => strlen($prompt) / 4,
            'output_tokens' => 500,
        ];
    }

    public function getName(): string
    {
        return 'mock';
    }

    public function getModel(): string
    {
        return 'mock-v1';
    }

    protected function generateMockResponse(string $prompt, array $options): string
    {
        $module = $options['module'] ?? 'general';

        return match ($module) {
            'active_learning' => $this->generateActiveLearningResponse($options),
            default => 'Mock AI response for: ' . substr($prompt, 0, 100),
        };
    }

    protected function generateActiveLearningResponse(array $options): string
    {
        return json_encode([
            [
                'title' => 'Quick Recall: Core Concepts',
                'type' => 'individual',
                'description' => 'Students recall and identify key concepts from the lecture content.',
                'instructions' => "Based on this week's lecture content, answer the following:\n\n1. List THREE key concepts covered in the lecture and write a one-sentence definition for each\n2. Identify ONE concept you found most challenging and explain why\n3. Give a real-world example where one of these concepts applies\n\nSubmit your response within 5 minutes.",
                'duration_minutes' => 5,
                'clo_codes' => [],
                'grouping_strategy' => null,
                'max_group_size' => null,
                'response_type' => 'text',
                'response_mode' => 'individual',
                'content_meta' => [
                    'content_focus' => 'general',
                    'difficulty' => 'introductory',
                    'expected_outcomes' => ['Recall key definitions from lecture', 'Self-identify knowledge gaps'],
                    'key_concepts' => ['concept recall', 'self-assessment'],
                ],
            ],
            [
                'title' => 'Case Study: Applying Lecture Concepts',
                'type' => 'group',
                'description' => 'Groups analyse a realistic scenario and apply lecture concepts to solve it.',
                'instructions' => "Your team has been hired as consultants. A mid-size organisation is experiencing issues directly related to the concepts covered in today's lecture.\n\nScenario:\nThe organisation has been operating with outdated practices and is now facing measurable negative outcomes. Your task is to diagnose the root causes using the frameworks and principles from this week's content.\n\nIn your group:\n1. Identify the TOP 3 issues in the scenario, mapping each to a specific concept from the lecture\n2. For each issue, explain WHY it is a problem using the theoretical framework covered\n3. Propose a concrete solution for each issue, referencing best practices from the lecture\n4. Estimate the impact of your proposed changes\n\nPrepare a brief group report (bullet points are fine) and be ready to present your top recommendation.",
                'duration_minutes' => 25,
                'clo_codes' => [],
                'grouping_strategy' => 'random',
                'max_group_size' => 4,
                'response_type' => 'text',
                'response_mode' => 'group',
                'content_meta' => [
                    'content_focus' => 'case_study',
                    'difficulty' => 'intermediate',
                    'expected_outcomes' => ['Apply theoretical concepts to realistic scenarios', 'Diagnose problems using lecture frameworks', 'Propose evidence-based solutions'],
                    'key_concepts' => ['problem diagnosis', 'framework application', 'solution design'],
                ],
            ],
            [
                'title' => 'Technical Problem: Hands-On Calculation',
                'type' => 'pair',
                'description' => 'Pairs work through a concrete technical problem applying lecture formulas and methods.',
                'instructions' => "Work with your partner to solve the following problem using the methods covered in today's lecture.\n\nProblem:\nYou are given the following data set from this week's topic. Using the formulas and techniques discussed in the lecture:\n\n1. Calculate the key metrics using the appropriate formula from the lecture\n2. Show your working step-by-step\n3. Interpret your results — what do they tell us?\n4. Compare your answer with another pair and discuss any differences\n\nSubmit your calculations and interpretation.",
                'duration_minutes' => 15,
                'clo_codes' => [],
                'grouping_strategy' => 'random',
                'max_group_size' => 2,
                'response_type' => 'text',
                'response_mode' => 'group',
                'content_meta' => [
                    'content_focus' => 'technical_problem',
                    'difficulty' => 'intermediate',
                    'expected_outcomes' => ['Apply lecture formulas correctly', 'Interpret quantitative results', 'Validate answers through peer comparison'],
                    'key_concepts' => ['quantitative analysis', 'formula application', 'result interpretation'],
                ],
            ],
            [
                'title' => 'Group Presentation & Class Discussion',
                'type' => 'whole_class',
                'description' => 'Groups present their case study findings and the class discusses different approaches.',
                'instructions' => "Each group presents their top recommendation from the case study (2 minutes max).\n\nWhile listening:\n- Note which lecture concepts each group referenced\n- Identify any solution you hadn't considered\n- Prepare one question for another group\n\nAfter all presentations, we will vote on the most practical solution.",
                'duration_minutes' => 10,
                'clo_codes' => [],
                'grouping_strategy' => null,
                'max_group_size' => null,
                'response_type' => 'none',
                'response_mode' => 'individual',
                'content_meta' => [
                    'content_focus' => 'general',
                    'difficulty' => 'intermediate',
                    'expected_outcomes' => ['Communicate solutions clearly', 'Evaluate peer approaches critically'],
                    'key_concepts' => ['peer learning', 'critical evaluation'],
                ],
            ],
            [
                'title' => 'Reflection: What Changed?',
                'type' => 'reflection',
                'description' => 'Students reflect on how their understanding evolved during the session.',
                'instructions' => "Reflect on today's session and write:\n\n1. Before this session, I thought... (your initial understanding of the topic)\n2. After the case study and problem-solving, I now understand... (what shifted)\n3. One thing I will do differently based on what I learned today is...\n4. One question I still want to explore further is...",
                'duration_minutes' => 5,
                'clo_codes' => [],
                'grouping_strategy' => null,
                'max_group_size' => null,
                'response_type' => 'reflection',
                'response_mode' => 'individual',
                'content_meta' => [
                    'content_focus' => 'general',
                    'difficulty' => 'introductory',
                    'expected_outcomes' => ['Articulate learning progression', 'Identify areas for further study'],
                    'key_concepts' => ['metacognition', 'self-directed learning'],
                ],
            ],
        ]);
    }

}
