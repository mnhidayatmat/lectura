<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeCheckOption extends Model
{
    protected $fillable = ['episode_check_id', 'label', 'is_correct', 'sort_order'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'sort_order' => 'integer'];
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(EpisodeCheck::class, 'episode_check_id');
    }
}
