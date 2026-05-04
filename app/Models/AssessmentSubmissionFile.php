<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSubmissionFile extends Model
{
    protected $fillable = [
        'assessment_submission_id', 'file_name', 'file_type',
        'file_size_bytes', 'storage_path', 'graded_file_path', 'graded_at', 'drive_file_id',
        'annotations', 'annotated_image_path', 'annotated_at',
    ];

    protected function casts(): array
    {
        return [
            'graded_at' => 'datetime',
            'annotated_at' => 'datetime',
            'annotations' => 'array',
        ];
    }

    public function isPdf(): bool
    {
        return str_contains(strtolower($this->file_type ?? ''), 'pdf')
            || str_ends_with(strtolower($this->file_name ?? ''), '.pdf');
    }

    public function isAnnotatable(): bool
    {
        $name = strtolower((string) $this->file_name);
        return str_ends_with($name, '.pdf')
            || str_ends_with($name, '.png')
            || str_ends_with($name, '.jpg')
            || str_ends_with($name, '.jpeg');
    }

    /**
     * Path the viewer/downloader should serve — graded copy when stamped,
     * otherwise the original upload.
     */
    public function viewablePath(): string
    {
        return $this->graded_file_path ?: $this->storage_path;
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubmission::class, 'assessment_submission_id');
    }
}
