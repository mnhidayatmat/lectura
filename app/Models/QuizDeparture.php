<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student left the live quiz (another app, tab or window) while a question was open
 * and unanswered. That question can no longer be answered, and the lecturer sees it.
 */
class QuizDeparture extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['quiz_participant_id', 'quiz_session_question_id', 'client'];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(QuizParticipant::class, 'quiz_participant_id');
    }

    public function sessionQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizSessionQuestion::class, 'quiz_session_question_id');
    }
}
