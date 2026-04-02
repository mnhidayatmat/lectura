<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuizSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'section_id', 'lecturer_id', 'quiz_folder_id', 'title', 'join_code',
        'category', 'mode', 'is_anonymous', 'status', 'settings',
        'available_from', 'available_until', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'settings' => 'array',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (! $session->join_code) {
                $session->join_code = strtoupper(Str::random(6));
            }
        });
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(QuizFolder::class, 'quiz_folder_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(QuizSessionQuestion::class)->orderBy('sort_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(QuizParticipant::class);
    }

    public function activeQuestion(): ?QuizSessionQuestion
    {
        return $this->sessionQuestions()->where('status', 'active')->first();
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['waiting', 'active', 'reviewing']);
    }

    public function isOffline(): bool
    {
        return $this->category === 'offline';
    }

    public function isOfflineOpen(): bool
    {
        if (! $this->isOffline()) {
            return false;
        }

        $now = now();

        return $this->available_from
            && $this->available_until
            && $now->gte($this->available_from)
            && $now->lte($this->available_until)
            && $this->status !== 'ended';
    }
}
