<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSubmissionFile extends Model
{
    protected $fillable = [
        'assessment_submission_id', 'file_name', 'file_type',
        'file_size_bytes', 'storage_path', 'drive_file_id',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubmission::class, 'assessment_submission_id');
    }
}
