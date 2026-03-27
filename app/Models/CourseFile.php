<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_folder_id',
        'course_id',
        'uploaded_by',
        'material_type',
        'file_name',
        'file_type',
        'file_size_bytes',
        'storage_path',
        'url',
        'description',
        'section_id',
        'week_number',
        'sort_order',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CourseFolder::class, 'course_folder_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(FileTag::class);
    }

    public function isLink(): bool
    {
        return $this->material_type === 'link';
    }

    public function isFile(): bool
    {
        return $this->material_type !== 'link';
    }

    public function scopeForWeek($query, int $week)
    {
        return $query->where('week_number', $week);
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->file_size_bytes;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor(log($bytes, 1024));
        $factor = min($factor, count($units));

        return round($bytes / (1024 ** $factor), 2) . ' ' . $units[$factor - 1];
    }
}
