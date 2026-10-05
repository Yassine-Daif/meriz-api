<?php

namespace App\Http\Controllers;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\RegenerateGroupCode;
use App\Http\Requests\Groups\GroupNameRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * {group} n'est résolu que parmi les groupes visibles par l'utilisateur
 * connecté (voir AppServiceProvider). La Policy revérifie.
 */
class GroupController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Group::class);

        $groups = Group::visibleTo($request->user())
            ->with('creator')
            ->withCount(['members', 'documents'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return GroupResource::collection($groups);
    }

    public function store(GroupNameRequest $request, CreateGroup $create): JsonResponse
    {
        Gate::authorize('create', Group::class);

        $group = $create->handle($request->user(), $request->validated('name'));

        return $this->resource($group)->response()->setStatusCode(201);
    }

    public function show(Group $group): GroupResource
    {
        Gate::authorize('view', $group);

        return $this->resource($group);
    }

    public function update(GroupNameRequest $request, Group $group): GroupResource
    {
        Gate::authorize('manage', $group);

        $group->update($request->validated());

        return $this->resource($group);
    }

    public function regenerateCode(Group $group, RegenerateGroupCode $regenerate): GroupResource
    {
        Gate::authorize('manage', $group);

        return $this->resource($regenerate->handle($group));
    }

    public function destroy(Group $group): Response
    {
        Gate::authorize('manage', $group);

        // Les documents partagés et les appartenances partent en cascade.
        $group->delete();

        return response()->noContent();
    }

    private function resource(Group $group): GroupResource
    {
        $group->load([
            'creator',
            'members' => fn ($query) => $query->orderBy('name')->orderBy('first_name'),
            'documents' => fn ($query) => $query
                ->select(['id', 'name', 'user_id', 'group_id', 'assignment_id', 'last_observed_at', 'created_at', 'updated_at'])
                ->orderByDesc('updated_at'),
        ])->loadCount(['members', 'documents']);

        return new GroupResource($group);
    }
}
