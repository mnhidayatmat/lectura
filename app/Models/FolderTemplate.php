<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'description',
        'structure',
        'is_default',
        'scope',
    ];

    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
