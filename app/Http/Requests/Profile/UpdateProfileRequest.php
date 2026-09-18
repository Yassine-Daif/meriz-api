<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Seuls les champs du profil se modifient ici. Email, mot de passe, rôle et
 * statut scolaire ne figurent pas dans les règles : ils sont ignorés.
 */
class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
            'bio_shared' => ['sometimes', 'boolean'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_shared' => ['sometimes', 'boolean'],
        ];
    }
}
