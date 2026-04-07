<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Section extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'course_id', 'academic_term_id', 'lecturer_id', 'name', 'code', 'invite_code',
        'capacity', 'schedule', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Section $section) {
            if (! $section->invite_code) {
                $section->invite_code = strtoupper(Str::random(8));
            }
        });
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function sectionStudents(): HasMany
    {
        return $this->hasMany(SectionStudent::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'section_students')
            ->withPivot(['enrolled_at', 'enrollment_method', 'is_active'])
            ->withTimestamps();
    }

    public function activeStudents(): BelongsToMany
    {
        return $this->students()->wherePivot('is_active', true);
    }
}
