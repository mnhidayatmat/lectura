<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenderJudge extends Model
{
    protected $fillable = [
        'tender_session_id', 'slot', 'user_id', 'guest_name', 'guest_token_hash', 'company', 'declared_at',
        'conflict_at', 'replaced_at', 'submitted_at', 'reconciled_at', 'entered_by', 'paper_photo_path',
    ];

    protected $hidden = ['guest_token_hash'];

    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'declared_at' => 'datetime',
            'conflict_at' => 'datetime',
            'replaced_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TenderSession::class, 'tender_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(TenderScore::class);
    }

    public function pitchScores(): HasMany
    {
        return $this->hasMany(TenderPitchScore::class);
    }

    public function displayName(): string
    {
        return $this->user?->name ?? $this->guest_name ?? 'Judge '.$this->slot;
    }

    public function isGuest(): bool
    {
        return $this->guest_token_hash !== null;
    }
}
