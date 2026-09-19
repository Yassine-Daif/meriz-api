<?php

namespace App\Http\Controllers;

use App\Actions\Assignments\CopyAssignmentBase;
use App\Http\Resources\DocumentResource;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssignmentCopyController extends Controller
{
    /**
     * Crée, dans les documents de l'utilisateur connecté, une copie
     * modifiable de la base du devoir.
     */
    public function store(Request $request, Assignment $assignment, CopyAssignmentBase $copy): JsonResponse
    {
        Gate::authorize('copyBase', $assignment);

        $document = $copy->handle($request->user(), $assignment);

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }
}
