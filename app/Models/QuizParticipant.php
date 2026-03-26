<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizParticipant extends Model
{
    protected $fillable = [
        'quiz_session_id', 'user_id', 'display_name', 'total_score', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'joined_at' => 'datetime',
        ];
    }

    public function quizSession(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizResponse::class);
    }
}
