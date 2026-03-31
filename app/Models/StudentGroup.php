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

    public function folders(): HasMany
    {
        return $this->hasMany(StudentGroupFolder::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GroupTask::class)->orderBy('due_date')->orderBy('created_at');
    }

    public function minutes(): HasMany
    {
        return $this->hasMany(GroupMinute::class)->orderByDesc('meeting_date');
    }

    public function sleepingPartnerReports(): HasMany
    {
        return $this->hasMany(GroupSleepingPartnerReport::class);
    }

    public function voteRounds(): HasMany
    {
        return $this->hasMany(GroupVoteRound::class)->latest();
    }

    public function activeVoteRound(): ?GroupVoteRound
    {
        return $this->voteRounds()->where('status', 'open')->first();
    }

    public function swapRequests(): HasMany
    {
        return $this->hasMany(GroupSwapRequest::class, 'from_group_id')
            ->orWhere('to_group_id', $this->id);
    }

    public function isMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }
}
