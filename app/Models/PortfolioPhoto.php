<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioPhoto extends Model
{
    use BelongsToTenant;

    public const CATEGORIES = [
        'lecture' => 'Lecture',
        'lab' => 'Lab Session',
        'tutorial' => 'Tutorial',
        'group_activity' => 'Group Activity',
        'presentation' => 'Presentation',
        'workshop' => 'Workshop',
        'field_trip' => 'Field Trip',
        'assessment' => 'Assessment',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id', 'course_id', 'section_id', 'user_id',
        'category', 'caption', 'description', 'file_path', 'thumbnail_path',
        'file_name', 'mime_type', 'file_size_bytes', 'week_number', 'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'week_number' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function getPublicUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail_path) {
            return asset('storage/' . $this->thumbnail_path);
        }
        return $this->public_url;
    }
}
