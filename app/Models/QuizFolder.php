<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizFolder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'lecturer_id', 'name', 'color', 'description',
    ];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class, 'quiz_folder_id');
    }

    /** Tailwind color classes for each palette option */
    public static array $colors = [
        'indigo' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'text' => 'text-indigo-700 dark:text-indigo-300', 'border' => 'border-indigo-200 dark:border-indigo-700', 'dot' => 'bg-indigo-500'],
        'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-300', 'border' => 'border-emerald-200 dark:border-emerald-700', 'dot' => 'bg-emerald-500'],
        'amber' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-300', 'border' => 'border-amber-200 dark:border-amber-700', 'dot' => 'bg-amber-500'],
        'rose' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-300', 'border' => 'border-red-200 dark:border-red-700', 'dot' => 'bg-red-500'],
        'teal' => ['bg' => 'bg-teal-100 dark:bg-teal-900/30', 'text' => 'text-teal-700 dark:text-teal-300', 'border' => 'border-teal-200 dark:border-teal-700', 'dot' => 'bg-teal-500'],
        'purple' => ['bg' => 'bg-purple-100 dark:bg-purple-900/30', 'text' => 'text-purple-700 dark:text-purple-300', 'border' => 'border-purple-200 dark:border-purple-700', 'dot' => 'bg-purple-500'],
        'slate' => ['bg' => 'bg-slate-100 dark:bg-slate-700', 'text' => 'text-slate-700 dark:text-slate-300', 'border' => 'border-slate-200 dark:border-slate-600', 'dot' => 'bg-slate-500'],
    ];

    public function colorClasses(): array
    {
        return self::$colors[$this->color] ?? self::$colors['indigo'];
    }
}
