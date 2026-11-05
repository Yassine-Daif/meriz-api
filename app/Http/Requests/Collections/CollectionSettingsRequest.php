<?php

namespace App\Http\Requests\Collections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $collection = $this->route('workspaceCollection');

        return $collection->user_id === $this->user()->id
            && ($collection->kind !== 'classrooms' || $this->user()->isTeacher());
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'year_start' => ['sometimes', 'nullable', 'integer', 'between:1900,2200'],
            'period_kind' => ['sometimes', 'required', Rule::in(['academic', 'calendar'])],
            'archived' => ['sometimes', 'required', 'boolean'],
            'item_ids' => ['prohibited'],
            'kind' => ['prohibited'],
        ];
    }
}
