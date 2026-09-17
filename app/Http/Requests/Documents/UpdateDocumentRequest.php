<?php

namespace App\Http\Requests\Documents;

use App\Rules\MaxBytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['bail', 'sometimes', 'required', 'string', new MaxBytes(config('documents.max_content_bytes')), 'json'],
        ];
    }
}
