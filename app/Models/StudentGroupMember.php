<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGroupMember extends Model
{
    protected $fillable = [
        'student_group_id', 'user_id', 'role', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
