<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpisodeRewind extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['episode_id', 'user_id', 'from_seconds', 'to_seconds'];

    protected function casts(): array
    {
        return ['from_seconds' => 'integer', 'to_seconds' => 'integer'];
    }
}
