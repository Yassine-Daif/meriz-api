<?php

namespace App\Http\Controllers;

use App\Actions\Overview\ListStudentAssignments;
use App\Actions\Overview\ListSubmissionsToGrade;
use App\Http\Requests\Overview\ToGradeRequest;
use App\Http\Resources\StudentAssignmentResource;
use App\Http\Resources\ToGradeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Vues d'ensemble, en lecture seule : chacune répond en un appel et en un
 * nombre de requêtes constant, quel que soit le nombre de classes.
 *
 * Les deux partent de l'utilisateur du jeton : aucun identifiant de classe,
 * de devoir ni d'élève n'est accepté du client. Il n'y a donc pas de
 * ressource unique à autoriser ici — la visibilité est portée par les
 * requêtes elles-mêmes, dans les actions.
 */
class OverviewController extends Controller
{
    public function toGrade(ToGradeRequest $request, ListSubmissionsToGrade $toGrade): AnonymousResourceCollection
    {
        return ToGradeResource::collection(
            $toGrade->handle($request->user(), $request->perPage()),
        );
    }

    public function myAssignments(Request $request, ListStudentAssignments $assignments): AnonymousResourceCollection
    {
        return StudentAssignmentResource::collection(
            $assignments->handle($request->user()),
        );
    }
}
