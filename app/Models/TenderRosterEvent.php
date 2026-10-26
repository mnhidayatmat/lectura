<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderRosterEvent extends Model
{
    public const KINDS = ['retrench', 'at_floor', 'hire', 'hire_back', 'pass'];

    protected $fillable = [
        'tender_session_id', 'kind', 'company', 'user_id', 'reason', 'created_by',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TenderSession::class, 'tender_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
