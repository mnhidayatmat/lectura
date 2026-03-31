<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupVoteRound extends Model
{
    protected $fillable = [
        'student_group_id', 'started_by', 'status', 'winner_id', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(GroupVote::class, 'vote_round_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function hasVoted(int $userId): bool
    {
        return $this->votes()->where('voter_id', $userId)->exists();
    }
}
