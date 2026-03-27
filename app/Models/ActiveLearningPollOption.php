<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveLearningPollOption extends Model
{
    protected $fillable = [
        'activity_id',
        'label',
        'sort_order',
        'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_correct' => 'boolean',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningActivity::class, 'activity_id');
    }
}
