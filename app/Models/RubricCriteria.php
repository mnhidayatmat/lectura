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

    /**
     * The level a mark reaches: the lowest-marked level whose marks are at
     * least the given mark (clicking a level when grading fills in its marks),
     * or the top level for a mark above every level.
     */
    public function levelFor(?float $marks): ?RubricLevel
    {
        if ($marks === null || $this->levels->isEmpty()) {
            return null;
        }

        $ordered = $this->levels->sortBy(fn (RubricLevel $level) => [(float) $level->marks, $level->sort_order]);

        return $ordered->first(fn (RubricLevel $level) => $marks <= (float) $level->marks + 0.0001)
            ?? $ordered->last();
    }
}
