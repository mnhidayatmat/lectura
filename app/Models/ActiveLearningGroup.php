<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActiveLearningGroup extends Model
{
    protected $fillable = [
        'active_learning_activity_id', 'attendance_session_id',
        'name', 'color_tag', 'sort_order',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningActivity::class, 'active_learning_activity_id');
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ActiveLearningGroupMember::class, 'active_learning_group_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'active_learning_group_members', 'active_learning_group_id', 'user_id')
            ->withPivot('role', 'assigned_at')
            ->withTimestamps();
    }

    public function facilitator(): ?User
    {
        return $this->students()->wherePivot('role', 'facilitator')->first();
    }

    public function memberCount(): int
    {
        return $this->members()->count();
    }
}
