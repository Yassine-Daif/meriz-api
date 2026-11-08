<?php

namespace App\Http\Requests\Auth;

use App\Enums\UsageCategory;
use App\Services\AcademicEmailChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', 'unique:users,email', function (string $attribute, mixed $value, Closure $fail) {
                if ($this->input('usage_category') === UsageCategory::Teacher->value
                    && ! app(AcademicEmailChecker::class)->isAcademic($value)) {
                    $fail(__('Pour vous inscrire comme professeur, utilisez votre adresse email scolaire ou universitaire.'));
                }
            }],
            // bcrypt ignore tout au-delà de 72 octets.
            'password' => ['required', 'string', Password::defaults(), 'max:72'],
            'device_name' => ['nullable', 'string', 'max:100'],
            // Facultatif pour les versions de bureau antérieures.
            'usage_category' => ['sometimes', 'required', Rule::enum(UsageCategory::class)],
        ];
    }
}
