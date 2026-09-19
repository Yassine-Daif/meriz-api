<?php

namespace App\Http\Requests\Assignments;

use App\Enums\AssignmentType;
use App\Models\Assignment;
use App\Rules\MaxBytes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    /**
     * L'autorisation passe avant la validation.
     */
    public function authorize(): bool
    {
        Gate::authorize('create', [Assignment::class, $this->route('classroom')]);

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
     * Données validées, date limite convertie en UTC pour ne pas perdre le
     * décalage horaire envoyé par le client.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function normalize(array $validated): array
    {
        if (isset($validated['due_at'])) {
            $validated['due_at'] = Carbon::parse($validated['due_at'])->utc();
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentData(): array
    {
        return self::normalize($this->validated());
    }

    /**
     * Règles communes à la création et à la modification.
     *
     * @return array<string, array<mixed>>
     */
    public static function fieldRules(): array
    {
        // Base et corrigé sont des contenus de document : JSON, taille bornée,
        // stockés tels quels.
        $content = ['bail', 'nullable', 'string', new MaxBytes(config('documents.max_content_bytes')), 'json'];

        return [
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['required', 'string', 'max:'.config('assignments.max_instructions_chars')],
            'type' => ['required', Rule::enum(AssignmentType::class)],
            'due_at' => ['nullable', 'date'],
            'base_content' => $content,
            'solution_content' => $content,
        ];
    }
}
