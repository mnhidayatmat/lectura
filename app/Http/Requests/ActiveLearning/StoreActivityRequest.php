<?php

declare(strict_types=1);

namespace App\Http\Requests\ActiveLearning;

use App\Models\ActiveLearningActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app('current_tenant');

        return $this->user()->hasRoleInTenant($tenant->id, ['lecturer', 'admin']);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(ActiveLearningActivity::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:65535'],
            'solution' => ['nullable', 'string', 'max:10000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'clo_ids' => ['nullable', 'array'],
            'clo_ids.*' => ['integer', 'exists:course_learning_outcomes,id'],
            'grouping_strategy' => ['nullable', Rule::in(ActiveLearningActivity::GROUPING_STRATEGIES)],
            'max_group_size' => ['nullable', 'integer', 'min:2', 'max:50'],
            'response_mode' => ['nullable', Rule::in(ActiveLearningActivity::RESPONSE_MODES)],
            'response_type' => ['nullable', Rule::in(ActiveLearningActivity::RESPONSE_TYPES)],
            'poll_options' => ['nullable', 'array', 'min:2', 'max:6'],
            'poll_options.*' => ['required', 'string', 'max:255'],
            'poll_multi_select' => ['nullable', 'boolean'],
            'poll_show_results' => ['nullable', 'boolean'],
            'expected_outcomes' => ['nullable', 'array', 'max:10'],
            'expected_outcomes.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
