<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'submission_id', 'user_id', 'strengths', 'missing_points',
        'misconceptions', 'revision_advice', 'improvement_tips',
        'performance_level', 'ai_generated', 'is_released', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated' => 'boolean',
            'is_released' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
