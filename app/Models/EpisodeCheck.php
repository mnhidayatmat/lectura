<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EpisodeCheck extends Model
{
    protected $fillable = ['episode_id', 'at_seconds', 'prompt', 'explanation'];

    protected function casts(): array
    {
        return ['at_seconds' => 'integer'];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(EpisodeCheckOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(EpisodeCheckAnswer::class);
    }
}
