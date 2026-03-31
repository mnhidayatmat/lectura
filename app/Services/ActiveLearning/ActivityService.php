<?php

declare(strict_types=1);

namespace App\Services\ActiveLearning;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\ActiveLearningPollOption;

class ActivityService
{
    public function addActivity(ActiveLearningPlan $plan, array $data): ActiveLearningActivity
    {
        $maxOrder = $plan->activities()->max('sort_order') ?? -1;

        $activity = ActiveLearningActivity::create([
            'active_learning_plan_id' => $plan->id,
            'sort_order' => $maxOrder + 1,
            'title' => $data['title'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'solution' => $data['solution'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'clo_ids' => $data['clo_ids'] ?? null,
            'materials' => $data['materials'] ?? null,
            'grouping_strategy' => $data['grouping_strategy'] ?? null,
            'max_group_size' => $data['max_group_size'] ?? null,
            'response_mode' => $data['response_mode'] ?? 'individual',
            'response_type' => $data['response_type'] ?? 'none',
            'poll_config' => $this->buildPollConfig($data),
            'content_meta' => $this->buildContentMeta($data),
            'ai_generated' => $data['ai_generated'] ?? false,
        ]);

        $this->syncPollOptions($activity, $data['poll_options'] ?? []);

        return $activity;
    }

    public function updateActivity(ActiveLearningActivity $activity, array $data): ActiveLearningActivity
    {
        $updateData = [];

        // Always update fields present in the validated data
        foreach (['title', 'type', 'description', 'instructions', 'solution', 'duration_minutes', 'clo_ids', 'materials', 'grouping_strategy', 'max_group_size', 'response_mode', 'response_type'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        $updateData['poll_config'] = $this->buildPollConfig($data);

        // Always update content_meta when expected_outcomes is provided
        if (array_key_exists('expected_outcomes', $data)) {
            $updateData['content_meta'] = $this->buildContentMeta($data, $activity->content_meta);
        }

        $activity->update($updateData);

        if (isset($data['poll_options'])) {
            $this->syncPollOptions($activity, $data['poll_options']);
        }

        return $activity->fresh();
    }

    public function removeActivity(ActiveLearningActivity $activity): void
    {
        $planId = $activity->active_learning_plan_id;
        $sortOrder = $activity->sort_order;

        $activity->delete();

        // Re-sequence remaining activities
        ActiveLearningActivity::where('active_learning_plan_id', $planId)
            ->where('sort_order', '>', $sortOrder)
            ->decrement('sort_order');
    }

    protected function buildContentMeta(array $data, ?array $existing = null): ?array
    {
        $outcomes = array_values(array_filter($data['expected_outcomes'] ?? []));
        $meta = $existing ?? [];

        if (! empty($outcomes)) {
            $meta['expected_outcomes'] = $outcomes;
        } else {
            unset($meta['expected_outcomes']);
        }

        return ! empty($meta) ? $meta : null;
    }

    protected function buildPollConfig(array $data): ?array
    {
        if (($data['response_type'] ?? null) !== 'mcq') {
            return null;
        }

        return [
            'multi_select' => ! empty($data['poll_multi_select']),
            'show_results' => ! empty($data['poll_show_results']),
        ];
    }

    protected function syncPollOptions(ActiveLearningActivity $activity, array $options): void
    {
        if (empty($options) || $activity->response_type !== 'mcq') {
            return;
        }

        $activity->pollOptions()->delete();

        foreach (array_values($options) as $i => $label) {
            if (trim($label) === '') {
                continue;
            }
            ActiveLearningPollOption::create([
                'activity_id' => $activity->id,
                'label' => trim($label),
                'sort_order' => $i,
            ]);
        }
    }
}
