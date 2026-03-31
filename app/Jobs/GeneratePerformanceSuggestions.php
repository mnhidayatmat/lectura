<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Course;
use App\Models\PerformanceAiSuggestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiServiceManager;
use App\Services\Performance\PerformanceAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePerformanceSuggestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        protected PerformanceAiSuggestion $suggestion,
        protected Course $course,
        protected ?User $student = null,
    ) {}

    public function handle(PerformanceAiService $service): void
    {
        app(AiServiceManager::class)->resetProvider();

        // Bind tenant context
        $tenant = Tenant::find($this->course->tenant_id);
        if ($tenant) {
            app()->instance('current_tenant', $tenant);
        }

        try {
            $content = $this->student
                ? $service->generateStudentSuggestions($this->student, $this->course)
                : $service->generateCourseSuggestions($this->course);

            $this->suggestion->update([
                'status' => 'completed',
                'content' => $content,
            ]);
        } catch (\Throwable $e) {
            Log::error('Performance AI suggestion failed', [
                'suggestion_id' => $this->suggestion->id,
                'error' => $e->getMessage(),
            ]);

            $this->suggestion->update(['status' => 'failed']);

            throw $e;
        }
    }
}
