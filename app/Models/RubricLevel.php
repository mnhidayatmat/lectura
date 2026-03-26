<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricLevel extends Model
{
    protected $fillable = [
        'rubric_criteria_id', 'label', 'description', 'marks', 'sort_order',
    ];

    public function criteria(): BelongsTo
    {
        return $this->belongsTo(RubricCriteria::class, 'rubric_criteria_id');
    }
}
