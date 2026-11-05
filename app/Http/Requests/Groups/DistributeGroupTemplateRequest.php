<?php

namespace App\Http\Requests\Groups;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class DistributeGroupTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('document'));

        return true;
    }

    public function rules(): array
    {
        return ['group_ids' => ['required', 'array', 'list', 'min:1', 'max:20'], 'group_ids.*' => ['required', 'ulid', 'distinct'], 'request_key' => ['required', 'uuid']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isEmpty() && Group::visibleTo($this->user())->whereKey($this->input('group_ids'))->count() !== count($this->input('group_ids'))) {
                $validator->errors()->add('group_ids', 'Un groupe sélectionné n’est pas accessible.');
            }
        }];
    }
}
