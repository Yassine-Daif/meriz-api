<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Lecture légère : aucun énoncé, modèle de base ou corrigé lors du suivi. */
class AssignmentInterfaceController extends Controller
{
    public function show(Assignment $assignment): JsonResponse
    {
        Gate::authorize('view', $assignment);

        return response()->json(['data' => ['interface_settings' => $assignment->interface_settings]])
            ->header('Cache-Control', 'no-store');
    }
}
