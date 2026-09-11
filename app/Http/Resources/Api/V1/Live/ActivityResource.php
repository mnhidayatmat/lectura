<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Live;

use App\Models\ActiveLearningActivity;
use App\Models\ActiveLearningPollOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An active-learning activity as shown to students. Never exposes poll answers or the solution.
 *
 * @mixin ActiveLearningActivity
 */
class ActivityResource extends JsonResource
{
    public function __construct($resource, private ?int $position = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $pollConfig = $this->poll_config ?? [];
        $responseType = $this->response_type ?? 'none';

        return [
            'id' => $this->id,
            'number' => $this->position,
            'title' => $this->title,
            'type' => $this->type,
            'type_label' => $this->type_badge['label'],
            'description' => $this->description,
            'instructions' => $this->instructions,
            'instructions_text' => $this->plainText($this->instructions ?: $this->description),
            'duration_minutes' => $this->duration_minutes !== null ? (int) $this->duration_minutes : null,
            'response_mode' => $this->response_mode ?? 'individual',
            'response_type' => $responseType,
            'multi_select' => (bool) ($pollConfig['multi_select'] ?? false),
            'max_length' => match ($responseType) {
                'reflection' => 500,
                'text' => 2000,
                default => null,
            },
            'poll_options' => $responseType === 'mcq'
                ? $this->pollOptions->map(fn (ActiveLearningPollOption $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                ])->values()->all()
                : [],
        ];
    }

    private function plainText(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        $text = preg_replace('/<\s*(br|\/p|\/li|\/h[1-6]|\/div)\s*\/?>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
