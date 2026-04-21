<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Assignment extends Model
{
    use BelongsToTenant, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'marking_mode', 'parent_id'])
            ->logOnlyDirty()
            ->useLogName('assignment')
            ->setDescriptionForEvent(fn (string $event) => "Assignment \"{$this->title}\" was {$event}");
    }

    protected $fillable = [
        'tenant_id', 'course_id', 'section_id', 'student_group_set_id',
        'created_by', 'parent_id',
        'title', 'description', 'type', 'total_marks', 'deadline',
        'allow_resubmission', 'max_resubmissions', 'marking_mode',
        'submission_type',
        'answer_scheme', 'answer_scheme_path', 'answer_scheme_filename', 'answer_scheme_drive_file_id',
        'instruction_file_path', 'instruction_filename', 'instruction_drive_file_id', 'instruction_drive_web_link',
        'status', 'clo_ids',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'parent_id');
    }

    public function subAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'parent_id')->orderBy('deadline');
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

    public function groups(): HasMany
    {
        return $this->hasMany(AssignmentGroup::class);
    }

    public function studentGroupSet(): BelongsTo
    {
        return $this->belongsTo(StudentGroupSet::class);
    }

    public function isGroupAssignment(): bool
    {
        return $this->type === 'group';
    }

    public function usesStudentGroupSet(): bool
    {
        return $this->isGroupAssignment() && $this->student_group_set_id !== null;
    }

    /**
     * Get the group a specific user belongs to for this assignment.
     * Returns a StudentGroup when linked to a StudentGroupSet, else legacy AssignmentGroup.
     */
    public function groupForUser(int $userId): StudentGroup|AssignmentGroup|null
    {
        if ($this->usesStudentGroupSet()) {
            return StudentGroup::where('student_group_set_id', $this->student_group_set_id)
                ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
                ->first();
        }

        return $this->groups()
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->first();
    }

    /**
     * Check if a user is a group leader for this assignment.
     * For StudentGroupSet-linked assignments the leader is whoever holds role='leader'
     * in the pivot — a value maintained by the in-group voting system (GroupVoteRound::closeRound
     * promotes the winner to role='leader') or by manual lecturer assignment.
     */
    public function isGroupLeader(int $userId): bool
    {
        if ($this->usesStudentGroupSet()) {
            $group = $this->groupForUser($userId);
            if (! $group instanceof StudentGroup) {
                return false;
            }
            return $group->members()
                ->where('user_id', $userId)
                ->where('role', 'leader')
                ->exists();
        }

        return $this->groups()
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId)->where('is_leader', true))
            ->exists();
    }

    public function assessmentItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(AssessmentItem::class, 'assessable');
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

    /**
     * Check if this is a parent assignment (has sub-assignments)
     */
    public function isParent(): bool
    {
        return $this->subAssignments()->count() > 0;
    }

    /**
     * Check if this is a sub-assignment
     */
    public function isSubAssignment(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Scope to get only top-level assignments (no parent)
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get total marks including all sub-assignments
     */
    public function getTotalMarksIncludingSubAssignmentsAttribute(): float
    {
        if (!$this->isParent()) {
            return (float) $this->total_marks;
        }

        return (float) $this->total_marks + $this->subAssignments->sum('total_marks');
    }

    /**
     * Get total submissions including all sub-assignments
     */
    public function getTotalSubmissionsIncludingSubAssignmentsAttribute(): int
    {
        if (!$this->isParent()) {
            return $this->submissions()->count();
        }

        $count = $this->submissions()->count();
        foreach ($this->subAssignments as $sub) {
            $count += $sub->submissions()->count();
        }

        return $count;
    }
}
