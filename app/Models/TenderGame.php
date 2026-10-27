<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Tender\TenderRules;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tender game: two companies compete across weekly sessions, scored by
 * student Judges and compiled by a student Leader, under its ruleset's rules.
 */
class TenderGame extends Model
{
    use BelongsToTenant;

    public const COMPANIES = ['A', 'B'];

    protected $fillable = [
        'tenant_id', 'course_id', 'section_id', 'title', 'ruleset', 'company_a_name', 'company_b_name', 'created_by',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenderMember::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TenderSession::class)->orderBy('sort_order');
    }

    public function rules(): TenderRules
    {
        return TenderRules::for($this->ruleset);
    }

    public function companyName(?string $company): string
    {
        return match ($company) {
            'A' => $this->company_a_name,
            'B' => $this->company_b_name,
            default => '',
        };
    }

    public function memberFor(?User $user): ?TenderMember
    {
        if (! $user) {
            return null;
        }

        return $this->members->firstWhere('user_id', $user->id);
    }
}
