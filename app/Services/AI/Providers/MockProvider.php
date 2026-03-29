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
            'teaching_plan' => $this->generateTeachingPlanResponse($options),
            'active_learning' => $this->generateActiveLearningResponse($options),
            default => 'Mock AI response for: ' . substr($prompt, 0, 100),
        };
    }

    protected function generateActiveLearningResponse(array $options): string
    {
        return json_encode([
            [
                'title' => 'Warm-Up: Concept Recall',
                'type' => 'individual',
                'description' => 'Students individually recall key concepts from the previous session.',
                'instructions' => 'Take 3 minutes to write down everything you remember about last week\'s topic. Then submit your response.',
                'duration_minutes' => 5,
                'clo_codes' => [],
                'grouping_strategy' => null,
                'max_group_size' => null,
                'response_type' => 'text',
                'response_mode' => 'individual',
            ],
            [
                'title' => 'Think-Pair-Share: Key Principles',
                'type' => 'pair',
                'description' => 'Students discuss and compare their understanding of core principles with a partner.',
                'instructions' => 'Pair up with the person next to you. Share your notes, identify common themes, and discuss any points of confusion. Be ready to share one insight with the class.',
                'duration_minutes' => 10,
                'clo_codes' => [],
                'grouping_strategy' => 'random',
                'max_group_size' => 2,
                'response_type' => 'text',
                'response_mode' => 'group',
            ],
            [
                'title' => 'Group Problem-Solving',
                'type' => 'group',
                'description' => 'Small groups collaborate to solve a practical problem applying the week\'s concepts.',
                'instructions' => 'In your group, work through the given scenario. Apply the concepts we discussed and prepare a short explanation of your approach.',
                'duration_minutes' => 20,
                'clo_codes' => [],
                'grouping_strategy' => 'random',
                'max_group_size' => 4,
                'response_type' => 'text',
                'response_mode' => 'group',
            ],
            [
                'title' => 'Class Discussion & Debrief',
                'type' => 'whole_class',
                'description' => 'Groups share their solutions and the class discusses different approaches.',
                'instructions' => 'Each group will present their approach in 2 minutes. Listen actively and note any approaches that differ from yours.',
                'duration_minutes' => 10,
                'clo_codes' => [],
                'grouping_strategy' => null,
                'max_group_size' => null,
                'response_type' => 'none',
                'response_mode' => 'individual',
            ],
            [
                'title' => 'Reflection: One-Minute Paper',
                'type' => 'reflection',
                'description' => 'Students reflect on their learning and identify remaining questions.',
                'instructions' => 'Write down: (1) The most important thing you learned today, and (2) One question you still have.',
                'duration_minutes' => 5,
                'clo_codes' => [],
                'grouping_strategy' => null,
                'max_group_size' => null,
                'response_type' => 'reflection',
                'response_mode' => 'individual',
            ],
        ]);
    }

    protected function generateTeachingPlanResponse(array $options): string
    {
        $topic = $options['topic'] ?? 'General Topic';
        $week = $options['week'] ?? 1;

        $activities = [
            'Think-Pair-Share: Students discuss key concepts in pairs',
            'Group Problem-Solving: Small groups work on practice problems',
            'Concept Mapping: Students create visual maps of topic relationships',
            'Mini Quiz: Quick formative check using live polling',
            'Case Study Analysis: Real-world application discussion',
            'Peer Teaching: Students explain concepts to each other',
        ];

        $onlineAlts = [
            'Discussion forum post on key concepts',
            'Interactive online quiz with immediate feedback',
            'Video annotation exercise',
            'Collaborative document editing',
        ];

        $checks = [
            'Exit ticket: 1-minute summary of key takeaway',
            'Quick poll: Check understanding of main concept',
            'Problem-solving checkpoint',
        ];

        return json_encode([
            'lesson_flow' => "**Introduction (10 min):** Review previous week and introduce {$topic}.\n\n**Core Content (30 min):** Explain key concepts with examples and demonstrations.\n\n**Active Learning (20 min):** Students engage in hands-on practice and group discussion.\n\n**Wrap-up (10 min):** Summarize key points and preview next week.",
            'active_learning' => array_slice($activities, 0, rand(2, 3)),
            'online_alternatives' => array_slice($onlineAlts, 0, rand(1, 2)),
            'formative_checks' => array_slice($checks, 0, rand(1, 2)),
            'time_allocation' => [
                'Introduction' => 10,
                'Core Content' => 30,
                'Active Learning' => 20,
                'Wrap-up' => 10,
            ],
            'assessment_notes' => "Week {$week} covers foundational concepts needed for upcoming assignments.",
        ]);
    }
}
