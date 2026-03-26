<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'assignment_id', 'user_id', 'submission_number',
        'notes', 'is_late', 'submitted_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_late' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }

    public function markingSuggestions(): HasMany
    {
        return $this->hasMany(MarkingSuggestion::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'submitted' => ['label' => 'Submitted', 'color' => 'blue'],
            'processing' => ['label' => 'Processing', 'color' => 'amber'],
            'marked' => ['label' => 'Marked', 'color' => 'emerald'],
            'reviewed' => ['label' => 'Reviewed', 'color' => 'purple'],
            default => ['label' => 'Pending', 'color' => 'slate'],
        };
    }
}
