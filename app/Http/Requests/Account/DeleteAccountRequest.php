<?php

namespace App\Http\Requests\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Confirmation forte avant une suppression définitive : le mot de passe
 * courant, vérifié contre le compte du jeton. Personne ne supprime le
 * compte d'un autre, puisqu'il n'y a pas d'identifiant à passer.
 */
class DeleteAccountRequest extends FormRequest
{
    public const WRONG_PASSWORD_MESSAGE = 'Mot de passe incorrect.';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('password')) {
                    return;
                }

                $given = (string) $this->input('password');

                if (! Hash::check($given, (string) $this->user()->password)) {
                    $validator->errors()->add('password', self::WRONG_PASSWORD_MESSAGE);
                }
            },
        ];
    }
}
