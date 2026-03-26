<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizResponse extends Model
{
    protected $fillable = [
        'quiz_session_question_id', 'quiz_participant_id', 'answer_text',
        'selected_option_id', 'is_correct', 'points_earned', 'response_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'points_earned' => 'decimal:2',
        ];
    }

    public function sessionQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizSessionQuestion::class, 'quiz_session_question_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(QuizParticipant::class, 'quiz_participant_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }
}
