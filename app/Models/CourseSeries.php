<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSeries extends Model
{
    use BelongsToTenant;

    protected $table = 'course_series';

    protected $fillable = [
        'tenant_id',
        'course_id',
        'title',
        'tagline',
        'description',
        'cover_path',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('episode_number')->orderBy('id');
    }

    public function publishedEpisodes(): HasMany
    {
        return $this->episodes()->where('status', Episode::STATUS_PUBLISHED);
    }
}
