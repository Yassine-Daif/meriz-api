<?php

namespace App\Http\Requests\Lessons;

use App\Models\Lesson;
use App\Rules\MaxBytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreLessonRequest extends FormRequest
{
    /**
     * L'autorisation passe avant la validation.
     */
    public function authorize(): bool
    {
        Gate::authorize('create', [Lesson::class, $this->route('classroom')]);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            // Page de blocs : JSON valide et taille bornée, rien de plus.
            // Le serveur ne regarde pas ce qu'il y a dedans.
            'blocks' => ['bail', 'required', 'string', new MaxBytes(config('lessons.max_blocks_bytes')), 'json'],
        ];
    }
}
