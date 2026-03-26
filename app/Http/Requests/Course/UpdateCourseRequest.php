<?php

declare(strict_types=1);

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');
        return $this->user()->id === $course->lecturer_id
            || $this->user()->hasRoleInTenant(app('current_tenant')->id, ['admin']);
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
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            'status' => ['nullable', 'in:draft,active,archived'],
        ];
    }
}
