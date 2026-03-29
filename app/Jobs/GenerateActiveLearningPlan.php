<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ActiveLearningPlan;
use App\Services\AI\ActiveLearningGeneratorService;
use App\Services\AI\AiServiceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateActiveLearningPlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        protected ActiveLearningPlan $plan,
        protected ?string $lectureNotesText = null,
        protected int $studentCount = 0,
    ) {}

    public function handle(ActiveLearningGeneratorService $generator): void
    {
        // Reset provider cache for queue context
        app(AiServiceManager::class)->resetProvider();

        // Bind tenant context for the job
        $this->plan->load('course');
        if ($this->plan->course?->tenant_id) {
            $tenant = \App\Models\Tenant::find($this->plan->course->tenant_id);
            if ($tenant) {
                app()->instance('current_tenant', $tenant);
            }
        }

        $this->plan->update(['ai_generation_status' => 'processing']);

        try {
            $generator->generate($this->plan, $this->lectureNotesText, $this->studentCount);

            $this->plan->update([
                'ai_generation_status' => 'draft_review',
                'ai_generated_at' => now(),
                'ai_prompt_summary' => mb_substr(
                    $this->lectureNotesText ?? 'Generated from topic and CLOs',
                    0,
                    500,
                ),
            ]);
        } catch (\Throwable $e) {
            Log::error('Active learning AI generation failed', [
                'plan_id' => $this->plan->id,
                'error' => $e->getMessage(),
            ]);

            $this->plan->update(['ai_generation_status' => 'failed']);

            throw $e;
        }
    }
}
