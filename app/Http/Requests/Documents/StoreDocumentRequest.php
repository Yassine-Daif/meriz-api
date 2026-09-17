<?php

namespace App\Http\Requests\Documents;

use App\Rules\MaxBytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Le serveur vérifie seulement que c'est du JSON, il ne lit pas sa structure.
            'content' => ['bail', 'required', 'string', new MaxBytes(config('documents.max_content_bytes')), 'json'],
        ];
    }
}
