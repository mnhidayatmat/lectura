<?php

declare(strict_types=1);

namespace App\Services\ActiveLearning;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPlan;
use App\Models\Course;
use App\Models\User;

class ActiveLearningPlanService
{
    public function createPlan(Course $course, User $creator, array $data): ActiveLearningPlan
    {
        return ActiveLearningPlan::create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'course_topic_id' => $data['course_topic_id'] ?? null,
            'week_number' => $data['week_number'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? 90,
            'status' => 'draft',
            'source' => $data['source'] ?? 'manual',
            'created_by' => $creator->id,
        ]);
    }

    public function updatePlan(ActiveLearningPlan $plan, array $data): ActiveLearningPlan
    {
        $plan->update([
            'course_topic_id' => $data['course_topic_id'] ?? $plan->course_topic_id,
            'week_number' => $data['week_number'] ?? $plan->week_number,
            'title' => $data['title'] ?? $plan->title,
            'description' => $data['description'] ?? $plan->description,
            'duration_minutes' => $data['duration_minutes'] ?? $plan->duration_minutes,
        ]);

        return $plan->fresh();
    }

    public function publishPlan(ActiveLearningPlan $plan): void
    {
        $plan->publish();
    }

    public function archivePlan(ActiveLearningPlan $plan): void
    {
        $plan->update(['status' => 'archived']);
    }

    public function reorderActivities(ActiveLearningPlan $plan, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $activityId) {
            ActiveLearningActivity::where('id', $activityId)
                ->where('active_learning_plan_id', $plan->id)
                ->update(['sort_order' => $index]);
        }
    }

    public function deletePlan(ActiveLearningPlan $plan): void
    {
        $plan->delete();
    }
}
