<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionStudent extends Model
{
    protected $fillable = [
        'section_id', 'user_id', 'enrolled_at', 'enrollment_method', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public const STAFF_ROLES = ['admin', 'coordinator', 'lecturer'];

    /**
     * Active enrolments of real students: a lecturer who also holds the student role in the
     * institution (to try the student side) stays enrolled but is left off class rosters.
     */
    public function scopeRoster(Builder $query): Builder
    {
        return $query->where('section_students.is_active', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('tenant_users')
                ->join('sections', 'sections.tenant_id', '=', 'tenant_users.tenant_id')
                ->whereColumn('sections.id', 'section_students.section_id')
                ->whereColumn('tenant_users.user_id', 'section_students.user_id')
                ->where('tenant_users.is_active', true)
                ->whereIn('tenant_users.role', self::STAFF_ROLES));
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
