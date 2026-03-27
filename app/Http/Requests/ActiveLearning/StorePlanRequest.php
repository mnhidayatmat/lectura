<?php

declare(strict_types=1);

namespace App\Http\Requests\ActiveLearning;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:5000'],
            'course_topic_id' => ['nullable', 'exists:course_topics,id'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:52'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ];
    }
}
