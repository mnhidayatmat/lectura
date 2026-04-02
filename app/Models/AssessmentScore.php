<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScore extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'assessment_id', 'user_id',
        'raw_marks', 'max_marks', 'weighted_marks',
        'percentage', 'is_computed',
    ];

    protected function casts(): array
    {
        return [
            'is_computed' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
