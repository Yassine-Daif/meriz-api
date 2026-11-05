<?php

namespace App\Http\Requests\Education;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteTeacherRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function authorize(): bool
    {
        $institution = $this->input('institution_id');

        return is_string($institution) && Str::isUlid($institution) && $this->user()->hasVerifiedEmail()
            && DB::table('education_referents')->where('user_id', $this->user()->id)->where('institution_id', $institution)->exists();
    }

    public function rules(): array
    {
        return ['institution_id' => ['required', 'ulid'], 'email' => ['required', 'string', 'email:rfc', 'max:255']];
    }
}
