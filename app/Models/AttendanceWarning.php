<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceWarning extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'course_id', 'user_id', 'policy_level',
        'absence_count', 'total_sessions', 'absence_percentage',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'absence_percentage' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
