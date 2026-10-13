<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short portrait clip cut from an episode. Students see it once the episode
 * itself is available to them, so a clip never runs ahead of the series.
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
    ];

    protected function casts(): array
    {
        return [
            'clip_number' => 'integer',
            'start_seconds' => 'integer',
            'duration_seconds' => 'integer',
            'video_size_bytes' => 'integer',
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
}
