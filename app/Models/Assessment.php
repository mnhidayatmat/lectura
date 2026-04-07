<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'course_id', 'parent_id', 'title', 'type', 'method',
        'weightage', 'total_marks', 'bloom_level', 'sort_order',
        'status', 'description', 'requires_submission', 'due_date',
        'instruction_file_path', 'instruction_file_name',
    ];

    protected function casts(): array
    {
        return [
            'requires_submission' => 'boolean',
            'due_date' => 'datetime',
        ];
    }

    public const TYPES = [
        'quiz', 'assignment', 'test', 'project',
        'presentation', 'lab', 'final_exam', 'other',
    ];

    public const METHODS = [
        'written', 'oral', 'practical', 'online', 'observation', 'portfolio',
    ];

    public const BLOOM_LEVELS = [
        'remember', 'understand', 'apply', 'analyze', 'evaluate', 'create',
    ];

    public const STATUSES = ['draft', 'active', 'completed'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Assessment::class, 'parent_id')->orderBy('sort_order');
    }

    public function clos(): BelongsToMany
    {
        return $this->belongsToMany(CourseLearningOutcome::class, 'assessment_clos')
            ->withPivot('weightage')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class)->orderBy('sort_order');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function cloScores(): HasMany
    {
        return $this->hasMany(AssessmentCloScore::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssessmentSubmission::class);
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'active' => ['label' => 'Active', 'color' => 'emerald'],
            'completed' => ['label' => 'Completed', 'color' => 'purple'],
            default => ['label' => 'Draft', 'color' => 'amber'],
        };
    }

    /**
     * Check if this is a parent assessment (has children)
     */
    public function isParent(): bool
    {
        return $this->children()->count() > 0;
    }

    /**
     * Check if this is a child assessment
     */
    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Scope to get only top-level assessments (no parent)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get total weightage including all children
     */
    public function getTotalWeightageIncludingChildrenAttribute(): float
    {
        if (!$this->isParent()) {
            return (float) $this->weightage;
        }

        return (float) $this->weightage + $this->children->sum('weightage');
    }

    /**
     * Get total marks including all children
     */
    public function getTotalMarksIncludingChildrenAttribute(): float
    {
        if (!$this->isParent()) {
            return (float) $this->total_marks;
        }

        return (float) $this->total_marks + $this->children->sum('total_marks');
    }
}
