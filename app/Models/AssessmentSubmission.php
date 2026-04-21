<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentSubmission extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'assessment_id', 'user_id', 'student_group_id',
        'notes', 'is_late', 'submitted_at', 'status', 'drive_folder_id',
    ];

    protected function casts(): array
    {
        return [
            'is_late' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studentGroup(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AssessmentSubmissionFile::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(AssessmentScore::class, 'assessment_submission_id');
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'graded' => ['label' => 'Graded', 'color' => 'emerald'],
            default => ['label' => 'Submitted', 'color' => 'blue'],
        };
    }
}
