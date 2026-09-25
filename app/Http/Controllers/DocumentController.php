<?php

namespace App\Http\Controllers;

use App\Actions\Documents\CreateDocument;
use App\Http\Requests\Documents\IndexDocumentsRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Le paramètre {document} est résolu dans les documents de l'utilisateur
 * connecté (voir AppServiceProvider). La Policy revérifie la propriété.
 */
class DocumentController extends Controller
{
    public function index(IndexDocumentsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Document::class);

        $documents = $request->user()->documents()
            ->select(['id', 'name', 'assignment_id', 'created_at', 'updated_at'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return DocumentResource::collection($documents);
    }

    public function store(StoreDocumentRequest $request, CreateDocument $createDocument): JsonResponse
    {
        Gate::authorize('create', Document::class);

        $document = $createDocument->handle($request->user(), $request->validated());

        return (new DocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        return new DocumentResource($document);
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document);

        $document->update($request->validated());

        return new DocumentResource($document);
    }

    public function destroy(Document $document): Response
    {
        Gate::authorize('delete', $document);

        $document->delete();

        return response()->noContent();
    }
}
