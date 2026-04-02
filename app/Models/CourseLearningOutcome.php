<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseLearningOutcome extends Model
{
    protected $fillable = ['course_id', 'code', 'description', 'bloom_level', 'sort_order'];

    public const BLOOM_LEVELS = [
        'remember', 'understand', 'apply', 'analyze', 'evaluate', 'create',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function programmeLearningOutcomes(): BelongsToMany
    {
        return $this->belongsToMany(ProgrammeLearningOutcome::class, 'clo_plo_mappings')
            ->withPivot('mapping_level')
            ->withTimestamps();
    }

    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'assessment_clos')
            ->withPivot('weightage')
            ->withTimestamps();
    }
}
