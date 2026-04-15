<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StudentMentorship extends Model
{
    use BelongsToTenant, LogsActivity, SoftDeletes;

    public const ROLE_ACADEMIC_TUTOR = 'academic_tutor';

    public const ROLE_LI_SUPERVISOR = 'li_supervisor';

    protected $fillable = [
        'tenant_id',
        'lecturer_id',
        'student_id',
        'role',
        'academic_term_id',
        'assigned_at',
        'ended_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['lecturer_id', 'student_id', 'role', 'academic_term_id', 'ended_at'])
            ->logOnlyDirty()
            ->useLogName('mentorship');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function liDetail(): HasOne
    {
        return $this->hasOne(LiSupervisionDetail::class, 'mentorship_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeAcademicTutor(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ACADEMIC_TUTOR);
    }

    public function scopeLiSupervisor(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_LI_SUPERVISOR);
    }

    public function isAcademicTutor(): bool
    {
        return $this->role === self::ROLE_ACADEMIC_TUTOR;
    }

    public function isLiSupervisor(): bool
    {
        return $this->role === self::ROLE_LI_SUPERVISOR;
    }

    public function roleLabel(): string
    {
        return $this->isLiSupervisor() ? 'LI Supervisor' : 'Academic Tutor';
    }
}
