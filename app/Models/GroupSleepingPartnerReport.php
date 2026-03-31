<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupSleepingPartnerReport extends Model
{
    protected $fillable = [
        'student_group_id', 'reported_user_id', 'description', 'is_reviewed',
    ];

    protected function casts(): array
    {
        return [
            'is_reviewed' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }
}
