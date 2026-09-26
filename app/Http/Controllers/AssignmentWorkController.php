<?php

namespace App\Http\Controllers;

use App\Actions\Assignments\StartAssignmentWork;
use App\Http\Resources\DocumentResource;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssignmentWorkController extends Controller
{
    /**
     * Commencer un devoir : renvoie le document de travail de l'élève,
     * rattaché au devoir, qu'il y ait une base ou non.
     *
     * 201 si le travail vient d'être créé, 200 s'il existait déjà.
     */
    public function start(Request $request, Assignment $assignment, StartAssignmentWork $start): JsonResponse
    {
        Gate::authorize('copyBase', $assignment);

        ['document' => $document, 'created' => $created] = $start->handle($request->user(), $assignment);

        return (new DocumentResource($document))
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }
}
