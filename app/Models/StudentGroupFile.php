<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentGroupFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_group_id', 'uploaded_by', 'file_name',
        'file_type', 'file_size_bytes', 'storage_path', 'description',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(StudentGroup::class, 'student_group_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->file_size_bytes;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB'];
        $factor = min((int) floor(log($bytes, 1024)), count($units));

        return round($bytes / (1024 ** $factor), 2) . ' ' . $units[$factor - 1];
    }
}
