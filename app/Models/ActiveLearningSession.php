<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ActiveLearningSession extends Model
{
    use BelongsToTenant;

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'join_code',
        'current_activity_id',
        'started_at',
        'ended_at',
        'created_by',
        'summary_data',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'summary_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (empty($session->join_code)) {
                $session->join_code = strtoupper(Str::random(6));
            }
        });
    }

    // ── Relationships ──

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningPlan::class, 'plan_id');
    }

    public function currentActivity(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningActivity::class, 'current_activity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActiveLearningSessionParticipant::class, 'session_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ActiveLearningResponse::class, 'session_id');
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isNotStarted(): bool
    {
        return $this->status === self::STATUS_NOT_STARTED;
    }

    public function participantCount(): int
    {
        return $this->participants()->count();
    }

    public function responseCountForActivity(int $activityId): int
    {
        return $this->responses()->where('activity_id', $activityId)->count();
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }
}
