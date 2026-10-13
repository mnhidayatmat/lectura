<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One spin of the random present-student wheel, kept so enrolled students can
 * watch it replay: the same names in the same order, landing on the same winner.
 */
class RandomWheelSpin extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'attendance_session_id', 'section_id', 'spun_by', 'winner_id',
        'candidates', 'winner_index', 'turns', 'duration_ms', 'spun_at',
    ];

    protected function casts(): array
    {
        return [
            'candidates' => 'array',
            'winner_index' => 'integer',
            'turns' => 'integer',
            'duration_ms' => 'integer',
            'spun_at' => 'datetime',
        ];
    }

    /**
     * Stored to the millisecond (the column is `timestamp(3)`): students' wheels join the spin
     * from `elapsed_ms`, and the default `Y-m-d H:i:s` format would put them up to a second ahead.
     */
    protected function spunAt(): Attribute
    {
        return Attribute::set(fn ($value) => $value === null ? null : $this->asDateTime($value)->format('Y-m-d H:i:s.v'));
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function spinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'spun_by');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
