<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScore extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'assessment_id', 'user_id',
        'assessment_submission_id', 'raw_marks', 'max_marks',
        'weighted_marks', 'percentage', 'is_computed',
        'is_released', 'released_at', 'feedback', 'criteria_marks',
        'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'is_computed' => 'boolean',
            'is_released' => 'boolean',
            'released_at' => 'datetime',
            'finalized_at' => 'datetime',
            'criteria_marks' => 'array',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubmission::class, 'assessment_submission_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
