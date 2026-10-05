<?php

namespace App\Http\Requests\Groups;

use App\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Création et renommage d'un groupe : seul le nom vient du client.
 */
class GroupNameRequest extends FormRequest
{
    /**
     * L'autorisation passe avant la validation : un membre qui tente de
     * renommer reçoit son refus avant tout message sur les données.
     */
    public function authorize(): bool
    {
        $group = $this->route('group');

        $group instanceof Group
            ? Gate::authorize('manage', $group)
            : Gate::authorize('create', Group::class);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
