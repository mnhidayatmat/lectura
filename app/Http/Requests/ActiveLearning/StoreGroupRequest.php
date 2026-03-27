<?php

declare(strict_types=1);

namespace App\Http\Requests\ActiveLearning;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app('current_tenant');

        return $this->user()->hasRoleInTenant($tenant->id, ['lecturer', 'admin']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'attendance_session_id' => ['nullable', 'exists:attendance_sessions,id'],
        ];
    }
}
