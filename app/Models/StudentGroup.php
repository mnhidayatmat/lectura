<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentGroup extends Model
{
    protected $fillable = [
        'student_group_set_id', 'name', 'color_tag', 'sort_order',
    ];

    public function groupSet(): BelongsTo
    {
        return $this->belongsTo(StudentGroupSet::class, 'student_group_set_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(StudentGroupMember::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(StudentGroupPost::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(StudentGroupFile::class);
    }

    public function leader(): ?User
    {
        return $this->students()->wherePivot('role', 'leader')->first();
    }
}
