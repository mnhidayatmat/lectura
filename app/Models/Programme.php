<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'faculty_id', 'name', 'code', 'sort_order'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(ProgrammeLearningOutcome::class)->orderBy('sort_order');
    }
}
