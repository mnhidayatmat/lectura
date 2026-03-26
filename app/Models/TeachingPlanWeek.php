<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingPlanWeek extends Model
{
    protected $fillable = [
        'teaching_plan_id', 'week_number', 'topic', 'lesson_flow',
        'duration_minutes', 'active_learning', 'online_alternatives',
        'formative_checks', 'time_allocation', 'assessment_notes', 'ai_generated',
    ];

    protected function casts(): array
    {
        return [
            'active_learning' => 'array',
            'online_alternatives' => 'array',
            'formative_checks' => 'array',
            'time_allocation' => 'array',
            'ai_generated' => 'boolean',
        ];
    }

    public function teachingPlan(): BelongsTo
    {
        return $this->belongsTo(TeachingPlan::class);
    }
}
