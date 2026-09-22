<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Firebase Cloud Messaging registration token for one install of Lectura Go.
 */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'personal_access_token_id', 'token', 'platform'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
