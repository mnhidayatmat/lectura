<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionStudent extends Model
{
    protected $fillable = [
        'section_id', 'user_id', 'enrolled_at', 'enrollment_method', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
