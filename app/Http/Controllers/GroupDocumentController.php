<?php

namespace App\Http\Controllers;

use App\Actions\Groups\CreateGroupDocument;
use App\Http\Requests\Groups\StoreGroupDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * L'espace commun d'un groupe. La lecture, la modification et la suppression
 * d'un document passent ensuite par les routes /api/documents, ouvertes aux
 * membres par DocumentPolicy.
 */
class GroupDocumentController extends Controller
{
    public function index(Group $group): AnonymousResourceCollection
    {
        Gate::authorize('view', $group);

        $documents = $group->documents()
            ->select(['id', 'name', 'user_id', 'group_id', 'assignment_id', 'last_observed_at', 'created_at', 'updated_at'])
            ->orderByDesc('updated_at')
            ->get();

        return DocumentResource::collection($documents);
    }

    public function store(StoreGroupDocumentRequest $request, Group $group, CreateGroupDocument $create): JsonResponse
    {
        Gate::authorize('createDocument', $group);

        $document = $create->handle($group, $request->user(), $request->validated());

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }
}
