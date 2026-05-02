<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionFile extends Model
{
    protected $fillable = [
        'submission_id', 'file_name', 'file_type',
        'file_size_bytes', 'storage_path', 'drive_file_id', 'status',
        'annotations', 'annotated_image_path', 'annotated_at',
    ];

    protected $casts = [
        'annotations' => 'array',
        'annotated_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function isAnnotatable(): bool
    {
        $name = strtolower((string) $this->file_name);
        return str_ends_with($name, '.pdf')
            || str_ends_with($name, '.png')
            || str_ends_with($name, '.jpg')
            || str_ends_with($name, '.jpeg');
    }
}
