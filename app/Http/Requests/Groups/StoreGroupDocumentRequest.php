<?php

namespace App\Http\Requests\Groups;

use App\Rules\MaxBytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreGroupDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('createDocument', $this->route('group'));

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Même contrat que les documents personnels : JSON valide, borné,
            // stocké tel quel.
            'content' => ['bail', 'required', 'string', new MaxBytes(config('documents.max_content_bytes')), 'json'],
        ];
    }
}
