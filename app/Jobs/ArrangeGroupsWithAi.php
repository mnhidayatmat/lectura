<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningGroup;
use App\Models\ActiveLearningGroupMember;
use App\Models\AttendanceSession;
use App\Services\AI\AiGroupingService;
use App\Services\AI\AiServiceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ArrangeGroupsWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        protected ActiveLearningActivity $activity,
        protected AttendanceSession $session,
        protected int $groupSize,
    ) {}

    public function handle(AiGroupingService $groupingService): void
    {
        // Reset provider cache for queue context
        app(AiServiceManager::class)->resetProvider();

        // Bind tenant context
        $plan = $this->activity->plan;
        $plan->load('course');
        if ($plan->course?->tenant_id) {
            $tenant = \App\Models\Tenant::find($plan->course->tenant_id);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }

        try {
            $suggestions = $groupingService->suggestGroups($this->session, $this->groupSize);

            // Clear existing groups
            $this->activity->groups()->delete();

            foreach ($suggestions as $index => $groupData) {
                $group = ActiveLearningGroup::create([
                    'active_learning_activity_id' => $this->activity->id,
                    'attendance_session_id' => $this->session->id,
                    'name' => $groupData['name'],
                    'sort_order' => $index,
                ]);

                foreach ($groupData['member_ids'] as $memberId) {
                    ActiveLearningGroupMember::create([
                        'active_learning_group_id' => $group->id,
                        'user_id' => $memberId,
                        'role' => $memberId === $groupData['facilitator_id'] ? 'facilitator' : 'member',
                        'assigned_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('AI group arrangement failed', [
                'activity_id' => $this->activity->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
