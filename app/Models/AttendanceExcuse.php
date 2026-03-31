<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceExcuse extends Model
{
    protected $fillable = [
        'attendance_record_id', 'user_id', 'reason', 'category',
        'attachment_path', 'attachment_filename', 'status',
        'reviewed_by', 'reviewed_at', 'reviewer_note',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForCourse(Builder $query, int $courseId): Builder
    {
        return $query->whereHas('record.session.section', function (Builder $q) use ($courseId) {
            $q->where('course_id', $courseId);
        });
    }
}
