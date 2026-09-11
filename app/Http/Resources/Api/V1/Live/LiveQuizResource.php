<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Live;

use App\Models\QuizParticipant;
use App\Models\QuizSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin QuizSession */
class LiveQuizResource extends JsonResource
{
    public function __construct($resource, private ?QuizParticipant $participant = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $section = $this->section;
        $course = $section?->course;

        $questionCount = match (true) {
            array_key_exists('question_count', $this->resource->getAttributes()) => (int) $this->resource->getAttributes()['question_count'],
            $this->resource->relationLoaded('sessionQuestions') => $this->sessionQuestions->count(),
            default => $this->sessionQuestions()->count(),
        };

        $answered = 0;
        if ($this->participant) {
            $attributes = $this->participant->getAttributes();
            $answered = array_key_exists('responses_count', $attributes)
                ? (int) $attributes['responses_count']
                : $this->participant->responses()->count();
        }

        $completed = $this->resource->isOffline()
            ? $questionCount > 0 && $answered >= $questionCount
            : in_array($this->status, ['reviewing', 'ended'], true);

        return [
            'id' => $this->id,
            'type' => 'quiz',
            'title' => $this->title,
            'category' => $this->category,
            'mode' => $this->mode,
            'mode_label' => Str::headline((string) $this->mode),
            'is_anonymous' => (bool) $this->is_anonymous,
            'status' => $this->status,
            'join_code' => $this->join_code,
            'course' => $course ? [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
            ] : null,
            'section' => $section ? [
                'id' => $section->id,
                'name' => $section->name,
            ] : null,
            'question_count' => $questionCount,
            'available_from' => $this->available_from?->toIso8601String(),
            'available_until' => $this->available_until?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'me' => [
                'joined' => $this->participant !== null,
                'display_name' => $this->participant?->display_name,
                'score' => (float) ($this->participant?->total_score ?? 0),
                'answered_count' => $answered,
                'completed' => $this->participant !== null && $completed,
            ],
        ];
    }
}
