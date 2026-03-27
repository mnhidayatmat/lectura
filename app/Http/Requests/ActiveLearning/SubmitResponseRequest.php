<?php

declare(strict_types=1);

namespace App\Http\Requests\ActiveLearning;

use Illuminate\Foundation\Http\FormRequest;

class SubmitResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_id' => ['required', 'integer', 'exists:active_learning_activities,id'],
            'response_data' => ['required', 'array'],
            'response_data.text' => ['nullable', 'string', 'max:2000'],
            'response_data.selected_options' => ['nullable', 'array'],
            'response_data.selected_options.*' => ['integer'],
            'group_id' => ['nullable', 'integer', 'exists:active_learning_groups,id'],
        ];
    }
}
