<?php

namespace App\Http\Requests\Education;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTeacherInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'size:64', 'regex:/^[a-zA-Z0-9]+$/']];
    }
}
