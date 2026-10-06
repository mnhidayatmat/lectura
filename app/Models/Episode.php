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

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isAvailable(): bool
    {
        return $this->isPublished() && ($this->publish_at === null || $this->publish_at->lte(now()));
    }

    /**
     * When students could (or can) first watch it: the scheduled time, or when it was created.
     */
    public function availableAt(): Carbon
    {
        return $this->publish_at ?? $this->created_at;
    }
}
