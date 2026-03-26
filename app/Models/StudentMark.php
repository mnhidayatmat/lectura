<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMark extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'assignment_id', 'submission_id', 'user_id',
        'total_marks', 'max_marks', 'percentage', 'grade',
        'is_final', 'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'is_final' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
