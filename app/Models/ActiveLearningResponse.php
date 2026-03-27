<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveLearningResponse extends Model
{
    use BelongsToTenant;

    public const TYPE_TEXT = 'text';
    public const TYPE_MCQ = 'mcq';
    public const TYPE_REFLECTION = 'reflection';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'activity_id',
        'user_id',
        'group_id',
        'response_type',
        'response_data',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'response_data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    // ── Relationships ──

    public function session(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningSession::class, 'session_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningActivity::class, 'activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ActiveLearningGroup::class, 'group_id');
    }

    // ── Helpers ──

    public function getTextContent(): ?string
    {
        return $this->response_data['text'] ?? null;
    }

    public function getSelectedOptions(): array
    {
        return $this->response_data['selected_options'] ?? [];
    }
}
