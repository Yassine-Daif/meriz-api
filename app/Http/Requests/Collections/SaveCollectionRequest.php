<?php

namespace App\Http\Requests\Collections;

use App\Models\Classroom;
use App\Models\Group;
use App\Models\WorkspaceCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $collection = $this->route('workspaceCollection');

        return (! $collection instanceof WorkspaceCollection || $collection->user_id === $this->user()->id)
            && ($this->input('kind') !== 'classrooms' || $this->user()->isTeacher());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'string', Rule::in(['classrooms', 'groups'])],
            'year_start' => ['nullable', 'integer', 'between:1900,2200'],
            'archived' => ['required', 'boolean'],
            'item_ids' => ['present', 'array', 'list', 'max:200'],
            'item_ids.*' => ['required', 'ulid', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $collection = $this->route('workspaceCollection');
            if ($collection instanceof WorkspaceCollection && $collection->kind !== $this->input('kind')) {
                $validator->errors()->add('kind', 'Le type du regroupement ne peut pas changer.');
            }
            $ids = $this->input('item_ids');
            $query = $this->input('kind') === 'classrooms'
                ? Classroom::where('teacher_id', $this->user()->id)
                : Group::visibleTo($this->user());
            if ($query->whereKey($ids)->count() !== count($ids)) {
                $validator->errors()->add('item_ids', 'Une classe ou un groupe sélectionné n’est pas accessible.');
            }
        }];
    }
}
