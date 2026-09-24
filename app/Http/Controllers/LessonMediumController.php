<?php

namespace App\Http\Controllers;

use App\Actions\Lessons\ManageLessonMedia;
use App\Http\Requests\Lessons\StoreLessonMediumRequest;
use App\Http\Resources\LessonMediumResource;
use App\Models\Lesson;
use App\Models\LessonMedium;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Médias des cours. Avec ceux des devoirs, ce sont les seules routes de
 * fichier du serveur. Le fichier vit sur le disque privé et n'est servi
 * qu'à qui peut voir le cours.
 */
class LessonMediumController extends Controller
{
    public function show(Lesson $lesson, LessonMedium $medium): BinaryFileResponse
    {
        Gate::authorize('view', $lesson);

        $disk = Storage::disk(config('lessons.disk'));

        abort_unless($disk->exists($medium->path), 404);

        // BinaryFileResponse gère les requêtes de plage : un lecteur audio
        // peut se déplacer dans la piste sans tout retélécharger.
        $response = response()->file($disk->path($medium->path), [
            'Content-Type' => $medium->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Content-Disposition' => 'inline; filename="'.$medium->name.'"',
            'Accept-Ranges' => 'bytes',
        ]);

        // setPrivate après coup : Symfony remet sinon le cache en « public »,
        // ce qui autoriserait un cache partagé à garder un média réservé.
        return $response->setPrivate()->setMaxAge(300);
    }

    public function store(StoreLessonMediumRequest $request, Lesson $lesson, ManageLessonMedia $media): JsonResponse
    {
        Gate::authorize('manage', $lesson);

        $medium = $media->store($lesson, $request->file('file'));

        return (new LessonMediumResource($medium))->response()->setStatusCode(201);
    }

    public function destroy(Lesson $lesson, LessonMedium $medium, ManageLessonMedia $media): Response
    {
        Gate::authorize('manage', $lesson);

        $media->delete($medium);

        return response()->noContent();
    }
}
