<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'section_id', 'lecturer_id', 'session_type',
        'week_number', 'qr_secret', 'qr_mode', 'qr_rotation_seconds',
        'late_threshold_minutes', 'status', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Why the session's attendance can no longer be changed, or null if it still can.
     * A closed semester locks every session of the sections running in it, an
     * archived course locks all of its sessions, and otherwise an ended session
     * locks once `lectura.attendance.lock_after_days` have passed.
     */
    public function lockReason(): ?string
    {
        if ($this->section?->term()?->isClosed()) {
            return 'semester_closed';
        }

        if ($this->section?->course?->status === 'archived') {
            return 'course_archived';
        }

        $days = (int) config('lectura.attendance.lock_after_days');

        if ($days > 0 && $this->status === 'ended' && $this->ended_at?->lt(now()->subDays($days))) {
            return 'edit_window_passed';
        }

        return null;
    }

    public function isLocked(): bool
    {
        return $this->lockReason() !== null;
    }

    public function lockMessage(): ?string
    {
        return match ($this->lockReason()) {
            'semester_closed' => 'This semester is closed, so its attendance is locked. Reopen the semester to make changes.',
            'course_archived' => 'This course is archived, so its attendance is locked. Restore the course to make changes.',
            'edit_window_passed' => 'This session ended more than '.(int) config('lectura.attendance.lock_after_days').' days ago, so its attendance is locked.',
            default => null,
        };
    }

    public function checkedInCount(): int
    {
        return $this->records()->whereIn('status', ['present', 'late'])->count();
    }
}
