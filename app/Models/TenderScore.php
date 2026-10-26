<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderScore extends Model
{
    protected $fillable = [
        'tender_judge_id', 'criterion', 'level_a', 'level_b', 'level_a_after', 'level_b_after', 'evidence',
    ];

    protected function casts(): array
    {
        return [
            'level_a' => 'integer',
            'level_b' => 'integer',
            'level_a_after' => 'integer',
            'level_b_after' => 'integer',
        ];
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(TenderJudge::class, 'tender_judge_id');
    }

    /** The level that counts: the reconciled one when the Judge changed it. */
    public function effective(string $company): ?int
    {
        return $company === 'A'
            ? ($this->level_a_after ?? $this->level_a)
            : ($this->level_b_after ?? $this->level_b);
    }
}
