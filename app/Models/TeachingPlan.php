<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingPlan extends Model
{
    protected $fillable = [
        'course_id', 'version', 'status', 'created_by', 'change_note',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(TeachingPlanWeek::class)->orderBy('week_number');
    }
}
