<?php

namespace App\Http\Requests\Comments;

use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCommentRequest extends FormRequest
{
    /**
     * L'autorisation passe avant la validation.
     */
    public function authorize(): bool
    {
        Gate::authorize('create', [Comment::class, $this->route('document')]);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bound = config('comments.position_bound');

        // Une bulle a ses deux coordonnées, un commentaire général n'en a
        // aucune. Le serveur ne regarde pas ce qu'elles veulent dire.
        $coordinate = ['nullable', 'numeric', "between:-{$bound},{$bound}"];

        return [
            'body' => ['required', 'string', 'max:'.config('comments.max_body_chars')],
            'position_x' => [...$coordinate, 'required_with:position_y'],
            'position_y' => [...$coordinate, 'required_with:position_x'],
        ];
    }
}
