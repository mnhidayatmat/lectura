<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseTopic extends Model
{
    protected $fillable = ['course_id', 'week_number', 'title', 'description', 'clo_ids', 'sort_order'];

    protected function casts(): array
    {
        return [
            'clo_ids' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
