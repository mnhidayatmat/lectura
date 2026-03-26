<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceChecklistItem extends Model
{
    protected $fillable = [
        'compliance_checklist_id',
        'title',
        'description',
        'rule_type',
        'rule_config',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rule_config' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ComplianceChecklist::class, 'compliance_checklist_id');
    }
}
