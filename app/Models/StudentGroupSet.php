<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentGroupSet extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'course_id', 'type', 'name', 'description',
        'creation_method', 'max_group_size', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public const TYPES = ['lecture', 'lab', 'tutorial'];

    public const CREATION_METHODS = ['manual', 'random', 'ai_suggested'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(StudentGroup::class)->orderBy('sort_order');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function totalMembers(): int
    {
        return StudentGroupMember::whereIn(
            'student_group_id',
            $this->groups()->pluck('id')
        )->distinct('user_id')->count('user_id');
    }
}
