<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Whiteboard extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const SCOPE_COURSE = 'course';
    public const SCOPE_GROUP = 'group';

    protected $fillable = [
        'tenant_id',
        'course_id',
        'active_learning_group_id',
        'scope',
        'title',
        'scene_data',
        'version',
        'created_by',
        'last_updated_by',
    ];

    protected $casts = [
        'scene_data' => 'array',
        'version' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningGroup::class, 'active_learning_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function isCourseScope(): bool
    {
        return $this->scope === self::SCOPE_COURSE;
    }

    public function isGroupScope(): bool
    {
        return $this->scope === self::SCOPE_GROUP;
    }
}
