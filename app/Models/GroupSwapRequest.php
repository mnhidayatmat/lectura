<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupSwapRequest extends Model
{
    protected $fillable = [
        'requester_id', 'target_user_id', 'from_group_id', 'to_group_id',
        'status', 'reject_reason', 'reviewed_by',
    ];

    public const STATUS_PENDING_MEMBER = 'pending_member';
    public const STATUS_PENDING_LECTURER = 'pending_lecturer';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function fromGroup(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'from_group_id');
    }

    public function toGroup(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'to_group_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
