<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricCriteria extends Model
{
    protected $table = 'rubric_criteria';

    protected $fillable = [
        'rubric_id', 'title', 'description', 'max_marks', 'weightage', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_marks' => 'float',
            'weightage' => 'float',
        ];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(RubricLevel::class)->orderBy('sort_order');
    }
}
