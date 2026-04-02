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
        'tenant_id', 'course_id', 'title', 'type', 'method',
        'weightage', 'total_marks', 'bloom_level', 'sort_order',
        'status', 'description',
    ];

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

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'active' => ['label' => 'Active', 'color' => 'emerald'],
            'completed' => ['label' => 'Completed', 'color' => 'purple'],
            default => ['label' => 'Draft', 'color' => 'amber'],
        };
    }
}
