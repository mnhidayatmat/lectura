<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on a clip. Replies are one level deep, as on TikTok: a reply to a
 * reply hangs off the same top-level comment and names who it answers.
 */
class ClipComment extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'episode_clip_id',
        'user_id',
        'parent_id',
        'reply_to_user_id',
        'body',
        'likes_count',
    ];

    protected function casts(): array
    {
        return ['likes_count' => 'integer'];
    }

    public function clip(): BelongsTo
    {
        return $this->belongsTo(EpisodeClip::class, 'episode_clip_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_to_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
