<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderMember extends Model
{
    public const ROLES = ['student', 'leader', 'deputy'];

    protected $fillable = [
        'tender_game_id', 'user_id', 'role', 'company', 'group_no', 'is_md', 'status',
    ];

    protected function casts(): array
    {
        return [
            'group_no' => 'integer',
            'is_md' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(TenderGame::class, 'tender_game_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runsSessions(): bool
    {
        return in_array($this->role, ['leader', 'deputy'], true);
    }

    public function isActiveStudent(): bool
    {
        return $this->role === 'student' && $this->status === 'active' && $this->company !== null;
    }

    public function isConsultant(): bool
    {
        return $this->role === 'student' && $this->status === 'consultant';
    }
}
