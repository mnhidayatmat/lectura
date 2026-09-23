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
            // CLOs — blank rows are skipped by the controller, so they must not fail validation
            'clos' => ['nullable', 'array'],
            'clos.*.code' => ['nullable', 'string', 'max:20', 'required_with:clos.*.description'],
            'clos.*.description' => ['nullable', 'string', 'max:1000', 'required_with:clos.*.code'],
            // Topics — a week left blank is skipped the same way
            'topics' => ['nullable', 'array'],
            'topics.*.week_number' => ['required_with:topics', 'integer', 'min:1'],
            'topics.*.title' => ['nullable', 'string', 'max:255'],
            'topics.*.description' => ['nullable', 'string', 'max:5000'],
            'topics.*.clos' => ['nullable', 'array'],
            'topics.*.clos.*' => ['string', 'max:20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'clos.*.code' => 'CLO code',
            'clos.*.description' => 'CLO description',
            'topics.*.title' => 'week topic title',
            'topics.*.description' => 'week subtopics',
        ];
    }
}
