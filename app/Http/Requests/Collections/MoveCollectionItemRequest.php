<?php

namespace App\Http\Requests\Collections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveCollectionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->input('kind') !== 'classrooms' || $this->user()->isTeacher();
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['classrooms', 'groups'])],
            'item_id' => ['required', 'ulid'],
            'from_id' => ['present', 'nullable', 'ulid'],
            'to_id' => ['present', 'nullable', 'ulid', 'different:from_id'],
        ];
    }
}
