<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupVote extends Model
{
    protected $fillable = [
        'vote_round_id', 'voter_id', 'nominee_id',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(GroupVoteRound::class, 'vote_round_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_id');
    }
}
