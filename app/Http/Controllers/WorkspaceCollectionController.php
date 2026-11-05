<?php

namespace App\Http\Controllers;

use App\Http\Requests\Collections\SaveCollectionRequest;
use App\Http\Resources\WorkspaceCollectionResource;
use App\Models\User;
use App\Models\WorkspaceCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceCollectionController extends Controller
{
    public function index(Request $request)
    {
        return WorkspaceCollectionResource::collection(
            WorkspaceCollection::visibleTo($request->user())->orderByRaw('year_start IS NULL')->orderByDesc('year_start')->orderBy('name')->get()
        );
    }

    public function store(SaveCollectionRequest $request)
    {
        $collection = DB::transaction(function () use ($request) {
            User::whereKey($request->user()->id)->lockForUpdate()->first();
            if (WorkspaceCollection::visibleTo($request->user())->count() >= 200) {
                throw ValidationException::withMessages(['name' => 'Nombre maximal de regroupements atteint.']);
            }
            $collection = new WorkspaceCollection($request->safe()->only(['name', 'year_start', 'archived']));
            $collection->forceFill(['user_id' => $request->user()->id, 'kind' => $request->validated('kind')])->save();
            $this->sync($collection, $request->validated('item_ids'));

            return $collection;
        });

        return (new WorkspaceCollectionResource($collection))->response()->setStatusCode(201);
    }

    public function update(SaveCollectionRequest $request, WorkspaceCollection $workspaceCollection)
    {
        DB::transaction(function () use ($request, $workspaceCollection) {
            $workspaceCollection->update($request->safe()->only(['name', 'year_start', 'archived']));
            $this->sync($workspaceCollection, $request->validated('item_ids'));
        });

        return new WorkspaceCollectionResource($workspaceCollection);
    }

    public function destroy(Request $request, WorkspaceCollection $workspaceCollection)
    {
        abort_unless($workspaceCollection->user_id === $request->user()->id, 404);
        $workspaceCollection->delete();

        return response()->noContent();
    }

    private function sync(WorkspaceCollection $collection, array $ids): void
    {
        $collection->kind === 'classrooms' ? $collection->classrooms()->sync($ids) : $collection->groups()->sync($ids);
    }
}
