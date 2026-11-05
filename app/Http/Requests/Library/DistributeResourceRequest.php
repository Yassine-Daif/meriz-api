<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistributeResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isTeacher();
    }

    public function rules(): array
    {
        return [
            'classroom_ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'classroom_ids.*' => ['required', 'ulid', 'distinct', Rule::exists('classrooms', 'id')->where('teacher_id', $this->user()->id)],
            'update_existing' => ['required', 'boolean'],
            'publish' => ['required', 'boolean'],
            'apply_settings' => ['required', 'boolean'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
