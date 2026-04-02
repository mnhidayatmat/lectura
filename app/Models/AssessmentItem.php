<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AssessmentItem extends Model
{
    protected $fillable = [
        'assessment_id', 'assessable_type', 'assessable_id',
        'contribution_percentage', 'sort_order',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function assessable(): MorphTo
    {
        return $this->morphTo();
    }
}
