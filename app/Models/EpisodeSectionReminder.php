<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * When a section was last reminded about an episode; the hourly limit is per section.
 */
class EpisodeSectionReminder extends Model
{
    public $timestamps = false;

    protected $fillable = ['episode_id', 'section_id', 'last_reminded_at'];

    protected function casts(): array
    {
        return ['last_reminded_at' => 'datetime'];
    }
}
