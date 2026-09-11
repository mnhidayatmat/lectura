<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Live;

use App\Models\QuestionOption;
use App\Models\QuizSessionQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A quiz question as the student sees it. Correct answers and the explanation are
 * only included when $revealAnswers is true (question closed, or result view).
 *
 * @mixin QuizSessionQuestion
 */
class QuizQuestionResource extends JsonResource
{
    public function __construct(
        $resource,
        private int $position,
        private int $total,
        private bool $revealAnswers = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $question = $this->question;
        $options = $question->options;

        $data = [
            'session_question_id' => $this->id,
            'number' => $this->position,
            'total' => $this->total,
            'type' => $question->question_type,
            'text' => $question->text,
            'points' => (float) $question->points,
            'time_limit' => (int) $question->time_limit_seconds,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'options' => $options->map(fn (QuestionOption $option) => array_merge([
                'id' => $option->id,
                'label' => $option->label,
                'text' => $option->text,
            ], $this->revealAnswers ? ['is_correct' => (bool) $option->is_correct] : []))->values()->all(),
        ];

        if ($this->revealAnswers) {
            $data['correct_option_id'] = $options->firstWhere('is_correct', true)?->id;
            $data['explanation'] = $question->explanation;
        }

        return $data;
    }
}
