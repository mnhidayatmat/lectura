<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentCloScore extends Model
{
    protected $fillable = [
        'assessment_id', 'course_learning_outcome_id', 'user_id',
        'marks', 'max_marks', 'percentage',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function clo(): BelongsTo
    {
        return $this->belongsTo(CourseLearningOutcome::class, 'course_learning_outcome_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
