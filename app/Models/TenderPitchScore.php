<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderPitchScore extends Model
{
    protected $fillable = [
        'tender_judge_id', 'consultant_id', 'relevant', 'credible', 'standards', 'communication',
    ];

    public function judge(): BelongsTo
    {
        return $this->belongsTo(TenderJudge::class, 'tender_judge_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function total(): int
    {
        return $this->relevant + $this->credible + $this->standards + $this->communication;
    }
}
