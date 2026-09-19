<?php

namespace App\Http\Controllers;

use App\Actions\Assignments\ManageAssignmentImage;
use App\Http\Requests\Assignments\AssignmentImageRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Seule route de fichier du serveur. L'image vit sur le disque privé et
 * n'est servie qu'à qui peut voir le devoir : le prof de la classe, ou un
 * membre une fois le devoir publié.
 */
class AssignmentImageController extends Controller
{
    public function show(Assignment $assignment): StreamedResponse
    {
        Gate::authorize('view', $assignment);

        $disk = Storage::disk(config('assignments.image_disk'));

        abort_unless($assignment->hasImage() && $disk->exists($assignment->image_path), 404);

        $extension = config('assignments.image_mimes')[$assignment->image_mime] ?? 'img';

        return $disk->response($assignment->image_path, 'image.'.$extension, [
            'Content-Type' => $assignment->image_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => 'private, max-age=300',
        ], 'inline');
    }

    public function store(AssignmentImageRequest $request, Assignment $assignment, ManageAssignmentImage $images): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        $images->store($assignment, $request->file('image'));

        return (new AssignmentResource($assignment->loadMissing('classroom')))->detailed();
    }

    public function destroy(Assignment $assignment, ManageAssignmentImage $images): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        $images->delete($assignment);

        return (new AssignmentResource($assignment->loadMissing('classroom')))->detailed();
    }
}
