<?php

namespace App\Http\Requests\Comments;

use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
            'parent_id' => ['nullable', 'ulid', 'prohibits:position_x,position_y', Rule::exists('comments', 'id')
                ->where('document_id', $this->route('document')->id)
                ->whereNull('parent_id')->whereNull('drawing')],
            'drawing' => ['nullable', 'array', 'min:2', 'max:64', 'prohibits:parent_id,position_x,position_y'],
            'drawing.*' => ['required', 'array:x,y'],
            'drawing.*.x' => ['required', 'numeric', "between:-{$bound},{$bound}"],
            'drawing.*.y' => ['required', 'numeric', "between:-{$bound},{$bound}"],
        ];
    }
}
