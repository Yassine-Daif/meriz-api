<?php

namespace App\Http\Controllers;

use App\Actions\Comments\CreateComment;
use App\Actions\Comments\ResolveComment;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * {document} et {comment} ne sont résolus que parmi ce que l'utilisateur
 * peut atteindre (voir AppServiceProvider). Les Policies revérifient.
 */
class CommentController extends Controller
{
    public function index(Document $document): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Comment::class, $document]);

        $comments = $document->comments()
            ->with(['author', 'resolver'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Document $document, CreateComment $create): JsonResponse
    {
        Gate::authorize('create', [Comment::class, $document]);

        $comment = $create->handle($document, $request->user(), $request->validated());

        return $this->resource($comment)->response()->setStatusCode(201);
    }

    public function resolve(Request $request, Comment $comment, ResolveComment $resolution): CommentResource
    {
        Gate::authorize('resolve', $comment);

        return $this->resource($resolution->resolve($comment, $request->user()));
    }

    public function reopen(Comment $comment, ResolveComment $resolution): CommentResource
    {
        Gate::authorize('resolve', $comment);

        return $this->resource($resolution->reopen($comment));
    }

    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }

    private function resource(Comment $comment): CommentResource
    {
        $comment->load(['author', 'resolver']);

        return new CommentResource($comment);
    }
}
