<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeCaption extends Model
{
    public const LANGUAGES = ['en' => 'English', 'ms' => 'Bahasa Melayu'];

    protected $fillable = ['episode_id', 'language', 'disk', 'path'];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function label(): string
    {
        return self::LANGUAGES[$this->language] ?? strtoupper($this->language);
    }
}
