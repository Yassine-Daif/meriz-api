<?php

namespace App\Http\Requests\Profile;

use App\Rules\HexColor;
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
     * Une seule forme en base : minuscules, et #abc développé en #aabbcc.
     */
    protected function prepareForValidation(): void
    {
        foreach (['avatar_bg', 'avatar_fg'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => HexColor::normalize($this->input($field))]);
            }
        }
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
            'avatar_bg' => ['sometimes', 'nullable', new HexColor],
            'avatar_fg' => ['sometimes', 'nullable', new HexColor],
        ];
    }
}
