<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Modification partielle. Envoyer null pour base_content ou solution_content
 * retire la base ou le corrigé.
 */
class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage', $this->route('assignment'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentData(): array
    {
        return StoreAssignmentRequest::normalize($this->validated());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = StoreAssignmentRequest::fieldRules();
        foreach ($rules as $field => &$fieldRules) {
            // Les enfants restent obligatoires quand le parent est envoyé.
            if (! str_contains($field, '.')) {
                array_unshift($fieldRules, 'sometimes');
            }
        }

        return $rules;
    }
}
