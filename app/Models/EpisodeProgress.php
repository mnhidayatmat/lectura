<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeProgress extends Model
{
    protected $table = 'episode_progress';

    protected $fillable = [
        'episode_id',
        'user_id',
        'position_seconds',
        'furthest_seconds',
        'completed_at',
        'last_watched_at',
    ];

    protected function casts(): array
    {
        return [
            'position_seconds' => 'integer',
            'furthest_seconds' => 'integer',
            'completed_at' => 'datetime',
            'last_watched_at' => 'datetime',
        ];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
