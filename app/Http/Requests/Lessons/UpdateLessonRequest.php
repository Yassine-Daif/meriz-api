<?php

namespace App\Http\Requests\Lessons;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage', $this->route('lesson'));

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_map(
            fn (array $rules) => ['sometimes', ...$rules],
            StoreLessonRequest::fieldRules(),
        );
    }
}
