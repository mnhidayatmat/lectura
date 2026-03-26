<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'course_id', 'section_id', 'created_by',
        'title', 'description', 'type', 'total_marks', 'deadline',
        'allow_resubmission', 'max_resubmissions', 'marking_mode',
        'answer_scheme', 'status', 'clo_ids',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'allow_resubmission' => 'boolean',
            'clo_ids' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rubric(): HasOne
    {
        return $this->hasOne(Rubric::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function studentMarks(): HasMany
    {
        return $this->hasMany(StudentMark::class);
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'published' => ['label' => 'Published', 'color' => 'emerald'],
            'closed' => ['label' => 'Closed', 'color' => 'slate'],
            'marking' => ['label' => 'Marking', 'color' => 'blue'],
            'completed' => ['label' => 'Completed', 'color' => 'purple'],
            default => ['label' => 'Draft', 'color' => 'amber'],
        };
    }
}
