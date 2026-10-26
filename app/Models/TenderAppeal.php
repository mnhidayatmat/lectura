<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderAppeal extends Model
{
    public const DECISIONS = ['upheld', 'dismissed'];

    protected $fillable = [
        'tender_session_id', 'user_id', 'company', 'body', 'decision', 'decision_note', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
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
}
