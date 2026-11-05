<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaptureResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isTeacher();
    }

    public function rules(): array
    {
        return ['source_kind' => ['required', 'string', Rule::in(['assignment', 'lesson'])], 'source_id' => ['required', 'ulid']];
    }
}
