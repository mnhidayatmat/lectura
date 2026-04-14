<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Course extends Model
{
    use BelongsToTenant, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'title', 'status', 'teaching_mode'])
            ->logOnlyDirty()
            ->useLogName('course')
            ->setDescriptionForEvent(fn (string $event) => "Course {$this->code} was {$event}");
    }

    protected $fillable = [
        'tenant_id', 'faculty_id', 'programme_id', 'academic_term_id',
        'lecturer_id', 'code', 'title', 'description', 'credit_hours',
        'num_weeks', 'teaching_mode', 'format', 'status', 'invite_code',
        'custom_start_date', 'custom_end_date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            if (! $course->invite_code) {
                $course->invite_code = strtoupper(Str::random(8));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'format' => 'array',
            'custom_start_date' => 'date',
            'custom_end_date' => 'date',
        ];
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(CourseLearningOutcome::class)->orderBy('sort_order');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(CourseTopic::class)->orderBy('week_number');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(CourseFolder::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CourseFile::class);
    }

    public function materialSections(): HasMany
    {
        return $this->hasMany(CourseMaterialSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function studentGroupSets(): HasMany
    {
        return $this->hasMany(StudentGroupSet::class);
    }

    public function activeLearningPlans(): HasMany
    {
        return $this->hasMany(ActiveLearningPlan::class)->latest();
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('sort_order');
    }

    public function attendancePolicy(): HasOne
    {
        return $this->hasOne(AttendancePolicy::class);
    }

    public function attendanceWarnings(): HasMany
    {
        return $this->hasMany(AttendanceWarning::class);
    }

    public function totalStudents(): int
    {
        return SectionStudent::whereIn('section_id', $this->sections()->pluck('id'))
            ->where('is_active', true)
            ->distinct('user_id')
            ->count('user_id');
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'active' => ['label' => 'Active', 'color' => 'emerald'],
            'inactive' => ['label' => 'Inactive', 'color' => 'red'],
            'archived' => ['label' => 'Archived', 'color' => 'slate'],
            default => ['label' => 'Draft', 'color' => 'amber'],
        };
    }
}
