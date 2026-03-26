<?php

declare(strict_types=1);

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app('current_tenant');
        return $this->user()->hasRoleInTenant($tenant->id, ['lecturer', 'admin']);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'credit_hours' => ['nullable', 'integer', 'min:1', 'max:20'],
            'num_weeks' => ['required', 'integer', 'min:1', 'max:52'],
            'teaching_mode' => ['required', 'in:face_to_face,online,hybrid'],
            'format' => ['nullable', 'array'],
            'format.lecture' => ['nullable', 'boolean'],
            'format.tutorial' => ['nullable', 'boolean'],
            'format.lab' => ['nullable', 'boolean'],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            // CLOs
            'clos' => ['nullable', 'array'],
            'clos.*.code' => ['required_with:clos', 'string', 'max:20'],
            'clos.*.description' => ['required_with:clos', 'string', 'max:1000'],
            // Topics
            'topics' => ['nullable', 'array'],
            'topics.*.week_number' => ['required_with:topics', 'integer', 'min:1'],
            'topics.*.title' => ['required_with:topics', 'string', 'max:255'],
        ];
    }
}
