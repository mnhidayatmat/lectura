<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActiveLearningActivity extends Model
{
    protected $fillable = [
        'active_learning_plan_id', 'sort_order', 'title', 'type',
        'description', 'instructions', 'duration_minutes',
        'clo_ids', 'materials', 'grouping_strategy',
        'max_group_size', 'response_mode', 'response_type',
        'poll_config', 'content_meta', 'ai_generated',
    ];

    protected function casts(): array
    {
        return [
            'clo_ids' => 'array',
            'materials' => 'array',
            'poll_config' => 'array',
            'content_meta' => 'array',
            'ai_generated' => 'boolean',
        ];
    }

    public const TYPES = [
        'individual', 'pair', 'group', 'discussion',
        'reflection', 'whole_class',
    ];

    public const CONTENT_FOCUS_TYPES = ['general', 'case_study', 'technical_problem', 'mixed'];

    public const GROUPING_STRATEGIES = [
        'random', 'attendance_based', 'manual',
    ];

    public const RESPONSE_MODES = ['individual', 'group'];

    public const RESPONSE_TYPES = ['none', 'text', 'mcq', 'reflection'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningPlan::class, 'active_learning_plan_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ActiveLearningGroup::class, 'active_learning_activity_id')->orderBy('sort_order');
    }

    public function pollOptions(): HasMany
    {
        return $this->hasMany(ActiveLearningPollOption::class, 'activity_id')->orderBy('sort_order');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ActiveLearningResponse::class, 'activity_id');
    }

    public function isGrouped(): bool
    {
        return in_array($this->type, ['pair', 'group']);
    }

    public function getContentFocusBadgeAttribute(): ?array
    {
        $focus = $this->content_meta['content_focus'] ?? null;

        return match ($focus) {
            'case_study' => ['label' => 'Case Study', 'color' => 'amber'],
            'technical_problem' => ['label' => 'Technical Problem', 'color' => 'indigo'],
            'mixed' => ['label' => 'Mixed', 'color' => 'violet'],
            default => null,
        };
    }

    public function getTypeBadgeAttribute(): array
    {
        return match ($this->type) {
            'individual' => ['label' => __('active_learning.type_individual'), 'color' => 'blue'],
            'pair' => ['label' => __('active_learning.type_pair'), 'color' => 'violet'],
            'group' => ['label' => __('active_learning.type_group'), 'color' => 'amber'],
            'discussion' => ['label' => __('active_learning.type_discussion'), 'color' => 'emerald'],
            'reflection' => ['label' => __('active_learning.type_reflection'), 'color' => 'sky'],
            'whole_class' => ['label' => __('active_learning.type_whole_class'), 'color' => 'rose'],
            default => ['label' => $this->type, 'color' => 'slate'],
        };
    }
}
