<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentGroupMember extends Model
{
    protected $fillable = [
        'assignment_group_id', 'user_id', 'is_leader',
    ];

    protected function casts(): array
    {
        return [
            'is_leader' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AssignmentGroup::class, 'assignment_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
