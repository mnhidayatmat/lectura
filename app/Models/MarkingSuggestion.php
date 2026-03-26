<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarkingSuggestion extends Model
{
    protected $fillable = [
        'submission_id', 'rubric_criteria_id', 'question_ref',
        'extracted_answer', 'suggested_marks', 'max_marks',
        'explanation', 'confidence', 'status', 'final_marks',
        'lecturer_note', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function criteria(): BelongsTo
    {
        return $this->belongsTo(RubricCriteria::class, 'rubric_criteria_id');
    }
}
