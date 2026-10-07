<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Episode extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /** Students see it locked ("Coming soon") until the lecturer sets a release time. */
    public const STATUS_LOCKED = 'locked';

    /** Statuses students can see in Watch, locked or not. */
    public const VISIBLE_STATUSES = [self::STATUS_PUBLISHED, self::STATUS_LOCKED];

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_YOUTUBE = 'youtube';

    protected $fillable = [
        'tenant_id',
        'course_series_id',
        'course_id',
        'course_topic_id',
        'uploaded_by',
        'episode_number',
        'week_number',
        'title',
        'synopsis',
        'status',
        'source',
        'youtube_video_id',
        'publish_at',
        'required_by',
        'notify_students',
        'allow_download',
        'announced_at',
        'last_reminded_at',
        'video_disk',
        'video_path',
        'video_mime',
        'video_size_bytes',
        'duration_seconds',
        'poster_path',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'required_by' => 'datetime',
            'notify_students' => 'boolean',
            'allow_download' => 'boolean',
            'announced_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'episode_number' => 'integer',
            'week_number' => 'integer',
            'video_size_bytes' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(CourseSeries::class, 'course_series_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CourseTopic::class, 'course_topic_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(EpisodeProgress::class);
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(EpisodeScene::class)->orderBy('start_seconds')->orderBy('sort_order');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(EpisodeCheck::class)->orderBy('at_seconds')->orderBy('id');
    }

    public function captions(): HasMany
    {
        return $this->hasMany(EpisodeCaption::class)->orderBy('language');
    }

    public function rewinds(): HasMany
    {
        return $this->hasMany(EpisodeRewind::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::VISIBLE_STATUSES);
    }

    public function isVisible(): bool
    {
        return in_array($this->status, self::VISIBLE_STATUSES, true);
    }

    public function isYouTube(): bool
    {
        return $this->source === self::SOURCE_YOUTUBE;
    }

    public function canDownload(): bool
    {
        return ! $this->isYouTube() && (bool) $this->allow_download;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isAvailable(): bool
    {
        return match ($this->status) {
            self::STATUS_PUBLISHED => $this->publish_at === null || $this->publish_at->lte(now()),
            // A locked episode opens only at a release time the lecturer has set.
            self::STATUS_LOCKED => $this->publish_at !== null && $this->publish_at->lte(now()),
            default => false,
        };
    }

    /**
     * When students could (or can) first watch it: the scheduled time, or when it was created;
     * null while it is locked with no release time.
     */
    public function availableAt(): ?Carbon
    {
        if ($this->status === self::STATUS_LOCKED && $this->publish_at === null) {
            return null;
        }

        return $this->publish_at ?? $this->created_at;
    }
}
