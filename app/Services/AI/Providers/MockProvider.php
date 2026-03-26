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
            default => 'Mock AI response for: ' . substr($prompt, 0, 100),
        };
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
