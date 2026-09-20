<?php

namespace App\Http\Requests\Submissions;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('grade', $this->route('submission'));

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Note libre : « 16/20 », « Acquis », « À revoir ».
            'grade' => ['required', 'string', 'max:'.config('submissions.max_grade_chars')],
            'feedback' => ['nullable', 'string', 'max:'.config('submissions.max_feedback_chars')],
        ];
    }
}
