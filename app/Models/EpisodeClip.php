<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A short portrait clip cut from an episode. It releases with its episode unless
 * it has its own `publish_at`, which can only hold it back: a clip never runs
 * ahead of the series.
 */
class EpisodeClip extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'episode_id',
        'course_id',
        'clip_number',
        'title',
        'topic',
        'takeaway',
        'start_seconds',
        'duration_seconds',
        'video_disk',
        'video_path',
        'video_mime',
        'video_size_bytes',
        'poster_path',
        'publish_at',
        'views_count',
        'likes_count',
        'comments_count',
        'saves_count',
    ];

    protected function casts(): array
    {
        return [
            'clip_number' => 'integer',
            'start_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'video_size_bytes' => 'integer',
            'publish_at' => 'datetime',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'saves_count' => 'integer',
        ];
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isAvailable(): bool
    {
        $episode = $this->episode;

        return $episode !== null && $episode->isVisible() && $episode->isAvailable()
            && ($this->publish_at === null || $this->publish_at->lte(now()));
    }

    /**
     * When students could first see it: the latest of its episode's release, its own
     * release time and its import (clips added to an old episode are still new).
     */
    public function releasedAt(): ?Carbon
    {
        $episodeAt = $this->episode?->availableAt();
        if ($episodeAt === null) {
            return null;
        }

        return collect([$episodeAt, $this->publish_at, $this->created_at])->filter()->max();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ClipComment::class);
    }
}
