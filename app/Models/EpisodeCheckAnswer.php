<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeCheckAnswer extends Model
{
    protected $fillable = [
        'episode_check_id',
        'user_id',
        'episode_check_option_id',
        'is_correct',
        'first_is_correct',
        'attempts',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'first_is_correct' => 'boolean',
            'attempts' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(EpisodeCheck::class, 'episode_check_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
