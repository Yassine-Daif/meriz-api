<?php

namespace App\Http\Requests\Submissions;

use App\Models\Submission;
use App\Rules\MaxBytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSubmissionRequest extends FormRequest
{
    /**
     * L'autorisation passe avant la validation : un rendu verrouillé ou un
     * devoir invisible répond avant tout message sur les données.
     */
    public function authorize(): bool
    {
        Gate::authorize('submit', [Submission::class, $this->route('assignment')]);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Travail de l'élève : JSON valide, taille bornée, stocké tel quel.
            'content' => ['bail', 'required', 'string', new MaxBytes(config('documents.max_content_bytes')), 'json'],
        ];
    }
}
