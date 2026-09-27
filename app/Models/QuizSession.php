<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'section_id', 'lecturer_id', 'quiz_folder_id', 'title', 'join_code',
        'category', 'mode', 'is_anonymous', 'status', 'settings',
        'available_from', 'available_until', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'settings' => 'array',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (! $session->join_code) {
                $session->join_code = strtoupper(Str::random(6));
            }
        });
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(QuizFolder::class, 'quiz_folder_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function sessionQuestions(): HasMany
    {
        return $this->hasMany(QuizSessionQuestion::class)->orderBy('sort_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(QuizParticipant::class);
    }

    public function activeQuestion(): ?QuizSessionQuestion
    {
        return $this->sessionQuestions()->where('status', 'active')->first();
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['waiting', 'active', 'reviewing']);
    }

    public function isOffline(): bool
    {
        return $this->category === 'offline';
    }

    /**
     * A closed quiz that nobody has ever taken — a master copy kept to be
     * started (replayed) later, not a finished run with results.
     */
    public function hasNeverRun(): bool
    {
        return $this->status === 'ended'
            && $this->started_at === null
            && ($this->participants_count ?? $this->participants()->count()) === 0;
    }

    public function assessmentItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(AssessmentItem::class, 'assessable');
    }

    public function isOfflineOpen(): bool
    {
        if (! $this->isOffline()) {
            return false;
        }

        $now = now();

        return $this->available_from
            && $this->available_until
            && $now->gte($this->available_from)
            && $now->lte($this->available_until)
            && $this->status !== 'ended';
    }

    /**
     * A fresh session with copies of the same questions, ready to run again.
     * The original keeps its participants and results.
     */
    public function copyForNewRun(User $lecturer): self
    {
        $this->loadMissing('sessionQuestions.question.options');

        return DB::transaction(function () use ($lecturer) {
            $offline = $this->isOffline();

            $copy = self::create([
                'tenant_id' => $this->tenant_id,
                'section_id' => $this->section_id,
                'lecturer_id' => $lecturer->id,
                'title' => $this->title,
                'category' => $this->category,
                'mode' => $this->mode,
                'is_anonymous' => $this->is_anonymous,
                'status' => $offline ? 'active' : 'waiting',
                'available_from' => $this->available_from,
                'available_until' => $this->available_until,
                'started_at' => $offline ? now() : null,
            ]);

            foreach ($this->sessionQuestions as $sessionQuestion) {
                $original = $sessionQuestion->question;

                $question = Question::create([
                    'tenant_id' => $this->tenant_id,
                    'created_by' => $lecturer->id,
                    'question_type' => $original->question_type,
                    'text' => $original->text,
                    'explanation' => $original->explanation,
                    'time_limit_seconds' => $original->time_limit_seconds,
                    'points' => $original->points,
                    'is_bank' => true,
                ]);

                foreach ($original->options as $option) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'label' => $option->label,
                        'text' => $option->text,
                        'is_correct' => $option->is_correct,
                        'sort_order' => $option->sort_order,
                    ]);
                }

                QuizSessionQuestion::create([
                    'quiz_session_id' => $copy->id,
                    'question_id' => $question->id,
                    'sort_order' => $sessionQuestion->sort_order,
                    'status' => $offline ? 'active' : 'pending',
                    'opened_at' => $offline ? now() : null,
                ]);
            }

            return $copy;
        });
    }
}
