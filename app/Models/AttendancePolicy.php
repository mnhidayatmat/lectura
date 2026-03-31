<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendancePolicy extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'course_id', 'tenant_id', 'mode', 'warning_thresholds',
        'bar_threshold', 'bar_action', 'include_late_as_absent',
        'notify_student', 'notify_lecturer',
    ];

    protected function casts(): array
    {
        return [
            'warning_thresholds' => 'array',
            'bar_threshold' => 'decimal:2',
            'include_late_as_absent' => 'boolean',
            'notify_student' => 'boolean',
            'notify_lecturer' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(AttendanceWarning::class, 'course_id', 'course_id');
    }
}
