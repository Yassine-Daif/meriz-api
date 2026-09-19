<?php

namespace App\Http\Requests\Assignments;

use App\Rules\ImageContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

/**
 * Images seulement. Le type est vérifié sur le contenu réel du fichier
 * (mimetypes lit les octets), pas sur son nom ni sur l'en-tête envoyé.
 */
class AssignmentImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manage', $this->route('assignment'));

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $mimes = array_keys(config('assignments.image_mimes'));

        return [
            'image' => [
                'bail',
                'required',
                File::image()->max(config('assignments.max_image_kb')),
                'mimetypes:'.implode(',', $mimes),
                new ImageContent($mimes),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.mimetypes' => ImageContent::MESSAGE,
        ];
    }
}
