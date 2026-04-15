<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiSupervisionDetail extends Model
{
    protected $fillable = [
        'mentorship_id',
        'company_name',
        'company_address',
        'industry_supervisor_name',
        'industry_supervisor_email',
        'industry_supervisor_phone',
        'period_start',
        'period_end',
        'placement_status',
        'logbook_drive_folder_id',
        'final_report_path',
        'final_evaluation_score',
        'supervisor_remarks',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'final_evaluation_score' => 'decimal:2',
        ];
    }

    public function mentorship(): BelongsTo
    {
        return $this->belongsTo(StudentMentorship::class, 'mentorship_id');
    }
}
