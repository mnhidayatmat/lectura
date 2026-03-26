<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'course_file_id',
        'tag_type',
        'tag_value',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(CourseFile::class, 'course_file_id');
    }
}
