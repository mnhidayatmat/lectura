<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeScene extends Model
{
    protected $fillable = ['episode_id', 'code', 'title', 'start_seconds', 'sort_order'];

    protected function casts(): array
    {
        return ['start_seconds' => 'integer', 'sort_order' => 'integer'];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }
}
