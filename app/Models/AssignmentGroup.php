<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssignmentGroup extends Model
{
    protected $fillable = [
        'assignment_id', 'name',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(AssignmentGroupMember::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignment_group_members')
            ->withPivot('is_leader')
            ->withTimestamps();
    }

    public function leader(): ?AssignmentGroupMember
    {
        return $this->members()->where('is_leader', true)->first();
    }

    public function leaderUser(): ?User
    {
        $leader = $this->leader();
        return $leader ? $leader->user : null;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'assignment_group_id');
    }
}
