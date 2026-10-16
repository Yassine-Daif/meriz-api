<?php

namespace App\Http\Requests\Assignments;

use App\Rules\UploadedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

/**
 * Images seulement. Le type est vérifié sur le contenu réel du fichier
 * (mimetypes lit les octets), pas sur son nom ni sur l'en-tête envoyé.
 *
 * UploadedMedia avec mustBeImage fait le dernier contrôle : getimagesize doit
 * reconnaître une image du même type, ce qui écarte un HTML ou un SVG
 * déguisés en .png.
 */
class AssignmentImageRequest extends FormRequest
{
    public const MESSAGE = 'Seules les images JPEG, PNG ou WebP sont acceptées.';

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
                new UploadedMedia($mimes, self::MESSAGE, mustBeImage: true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimetypes' => self::MESSAGE,
        ];
    }
}
