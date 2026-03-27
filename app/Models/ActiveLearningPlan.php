<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActiveLearningPlan extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'course_id', 'course_topic_id', 'week_number',
        'title', 'description', 'duration_minutes',
        'status', 'source', 'ai_generation_status',
        'ai_generated_at', 'ai_prompt_summary',
        'created_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CourseTopic::class, 'course_topic_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActiveLearningActivity::class)->orderBy('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ActiveLearningSession::class, 'plan_id');
    }

    public function activeSession(): ?ActiveLearningSession
    {
        return $this->sessions()->where('status', ActiveLearningSession::STATUS_ACTIVE)->first();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isAiGenerated(): bool
    {
        return $this->source === 'ai_generated';
    }

    public function isAiProcessing(): bool
    {
        return $this->ai_generation_status === 'processing';
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function scopeForCourse(Builder $query, int $courseId): Builder
    {
        return $query->where('course_id', $courseId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'published' => ['label' => __('active_learning.status_published'), 'color' => 'emerald'],
            'archived' => ['label' => __('active_learning.status_archived'), 'color' => 'slate'],
            default => ['label' => __('active_learning.status_draft'), 'color' => 'amber'],
        };
    }
}
