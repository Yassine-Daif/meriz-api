<?php

namespace App\Http\Requests\Classrooms;

use App\Models\Classroom;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Création et renommage d'une classe : seul le nom vient du client.
 */
class ClassroomNameRequest extends FormRequest
{
    /**
     * L'autorisation passe avant la validation : un non-prof ou un membre
     * reçoit son refus (403) avant tout message sur les données envoyées.
     */
    public function authorize(): bool
    {
        $classroom = $this->route('classroom');

        $classroom instanceof Classroom
            ? Gate::authorize('manage', $classroom)
            : Gate::authorize('create', Classroom::class);

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
