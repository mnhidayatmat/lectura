<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Tender\TenderCriteria;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenderSession extends Model
{
    public const STATUSES = ['setup', 'scoring', 'compiled', 'reconcile', 'announced'];

    protected $fillable = [
        'tender_game_id', 'code', 'week', 'sort_order', 'status', 'first_company',
        'change_order_note', 'scoring_started_at', 'reconcile_started_at', 'reconcile_criteria', 'reconciled_at',
        'announced_at', 'winner', 'tie_break', 'sudden_death_winner', 'result', 'review_status',
        'review_comment', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'week' => 'integer',
            'scoring_started_at' => 'datetime',
            'reconcile_started_at' => 'datetime',
            'reconcile_criteria' => 'array',
            'reconciled_at' => 'datetime',
            'announced_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'result' => 'array',
        ];
    }

    /**
     * Sessions carry no tenant_id; binding through the game keeps another
     * institution's session a 404.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'id', $value)->whereHas('game')->firstOrFail();
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(TenderGame::class, 'tender_game_id');
    }

    public function judges(): HasMany
    {
        return $this->hasMany(TenderJudge::class)->orderBy('slot');
    }

    /** Judges still holding their slot (a conflict or substitution replaces one). */
    public function activeJudges(): HasMany
    {
        return $this->judges()->whereNull('replaced_at');
    }

    public function rosterEvents(): HasMany
    {
        return $this->hasMany(TenderRosterEvent::class)->orderBy('id');
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(TenderAppeal::class)->latest();
    }

    public function isGrandFinal(): bool
    {
        return $this->code === 'GF';
    }

    public function label(): string
    {
        return TenderCriteria::sessionLabel($this->code);
    }

    /** @return array<string, float> criterion => maximum marks */
    public function criteria(): array
    {
        return TenderCriteria::forSession($this->code);
    }

    public function retrenches(): bool
    {
        return in_array($this->code, ['S1', 'S2', 'S3', 'S4', 'S5'], true);
    }

    /** Appeals close at noon, institution time, the day after the announcement. */
    public function appealDeadline(): ?CarbonInterface
    {
        if (! $this->announced_at) {
            return null;
        }

        $tz = $this->game?->tenant?->timezone ?: config('app.timezone');

        return $this->announced_at->copy()->setTimezone($tz)->addDay()->setTime(12, 0)->utc();
    }
}
