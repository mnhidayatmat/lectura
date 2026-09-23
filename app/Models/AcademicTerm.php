<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'code', 'start_date', 'end_date', 'is_default', 'closed_at'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_default' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Running today (between its start and end dates, inclusive).
     */
    public function isCurrent(): bool
    {
        return $this->start_date && now()->gte($this->start_date)
            && (! $this->end_date || now()->lte($this->end_date->copy()->endOfDay()));
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
