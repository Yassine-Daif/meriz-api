<?php

namespace App\Http\Requests\Collections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrepareAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isTeacher() && $this->route('workspaceCollection')->kind === 'classrooms';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'year_start' => ['required', 'integer', 'between:1900,2200'],
            'request_key' => ['required', 'uuid'],
            'resource_ids' => ['present', 'array', 'list', 'max:50'],
            'resource_ids.*' => ['required', 'ulid', 'distinct', Rule::exists('teaching_resources', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
