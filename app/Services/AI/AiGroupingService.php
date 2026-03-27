<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Support\Collection;

class AiGroupingService
{
    public function __construct(
        protected AiServiceManager $ai,
    ) {}

    /**
     * Suggest group compositions using AI based on attendance data.
     *
     * @return array<array<int>> Array of arrays of user IDs
     */
    public function suggestGroups(AttendanceSession $session, int $targetGroupSize, array $options = []): array
    {
        $studentIds = $session->records()
            ->whereIn('status', ['present', 'late'])
            ->pluck('user_id');

        $students = User::whereIn('id', $studentIds)->get(['id', 'name', 'email']);

        if ($students->isEmpty()) {
            return [];
        }

        $prompt = $this->buildGroupingPrompt($students, $targetGroupSize, $options);

        $result = $this->ai->complete($prompt, [
            'module' => 'active_learning',
        ]);

        return $this->parseGroupingResponse($result['content'] ?? '', $students);
    }

    protected function buildGroupingPrompt(Collection $students, int $targetGroupSize, array $options): string
    {
        $studentList = $students->map(fn (User $s) => "- ID:{$s->id} Name:{$s->name}")->implode("\n");
        $totalStudents = $students->count();
        $numGroups = (int) ceil($totalStudents / $targetGroupSize);

        return "You are a classroom management assistant. Arrange the following {$totalStudents} students into approximately {$numGroups} balanced groups of about {$targetGroupSize} students each.

Students:
{$studentList}

Requirements:
- Each student must appear in exactly one group
- Groups should be as evenly sized as possible
- Assign one facilitator per group

Respond ONLY with a JSON array of groups:
[
  {
    \"group_name\": \"Group 1\",
    \"facilitator_id\": 123,
    \"member_ids\": [123, 456, 789]
  }
]";
    }

    /**
     * @return array<array<int>>
     */
    protected function parseGroupingResponse(string $content, Collection $students): array
    {
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $content = $matches[0];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            return [];
        }

        $validIds = $students->pluck('id')->all();
        $groups = [];

        foreach ($decoded as $group) {
            $memberIds = $group['member_ids'] ?? [];
            $filteredIds = array_values(array_filter(
                $memberIds,
                fn ($id) => in_array($id, $validIds)
            ));

            if (! empty($filteredIds)) {
                $groups[] = [
                    'name' => $group['group_name'] ?? 'Group ' . (count($groups) + 1),
                    'facilitator_id' => in_array($group['facilitator_id'] ?? null, $filteredIds)
                        ? $group['facilitator_id']
                        : $filteredIds[0],
                    'member_ids' => $filteredIds,
                ];
            }
        }

        return $groups;
    }
}
