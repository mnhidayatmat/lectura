<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProgrammeLearningOutcome extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'programme_id', 'code', 'description', 'domain', 'sort_order',
    ];

    public const DOMAINS = [
        'knowledge', 'practical_skill', 'social_skill',
        'professional_ethics', 'communication', 'critical_thinking',
        'teamwork', 'scientific_method', 'lifelong_learning', 'entrepreneurship',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function courseLearningOutcomes(): BelongsToMany
    {
        return $this->belongsToMany(CourseLearningOutcome::class, 'clo_plo_mappings')
            ->withPivot('mapping_level')
            ->withTimestamps();
    }
}
