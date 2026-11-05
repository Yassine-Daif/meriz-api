<?php

namespace App\Http\Controllers;

use App\Http\Requests\Collections\MoveCollectionItemRequest;
use App\Models\Classroom;
use App\Models\Group;
use App\Models\User;
use App\Models\WorkspaceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionMoveController extends Controller
{
    public function store(MoveCollectionItemRequest $request)
    {
        DB::transaction(function () use ($request) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $kind = $request->validated('kind');
            $item = $kind === 'classrooms'
                ? Classroom::where('teacher_id', $request->user()->id)->findOrFail($request->validated('item_id'))
                : Group::visibleTo($request->user())->findOrFail($request->validated('item_id'));
            $own = fn () => WorkspaceCollection::visibleTo($request->user())->where('kind', $kind);
            $from = $request->validated('from_id') ? $own()->findOrFail($request->validated('from_id')) : null;
            $to = $request->validated('to_id') ? $own()->findOrFail($request->validated('to_id')) : null;
            $relation = $kind === 'classrooms' ? 'classrooms' : 'groups';
            // Sans source : ajouter un classement sans retirer d'autres liens,
            // notamment pour annuler un retrait depuis un espace multi-classé.
            $atSource = $from ? $from->$relation()->whereKey($item->id)->exists() : true;
            $atTarget = $to && $to->$relation()->whereKey($item->id)->exists();
            // Une nouvelle tentative après une réponse perdue est sans effet.
            if (! $atSource) {
                if ($atTarget || ($to === null && $from !== null)) {
                    return;
                }
                throw ValidationException::withMessages(['item_id' => 'Le classement a changé. Actualisez la liste avant de déplacer cet espace.']);
            }
            if ($to && ! $atTarget && $to->$relation()->count() >= 200) {
                throw ValidationException::withMessages(['to_id' => 'Ce regroupement contient déjà 200 espaces.']);
            }
            if ($to) {
                $to->$relation()->syncWithoutDetaching([$item->id]);
            }
            if ($from) {
                $from->$relation()->detach($item->id);
            }
        });

        return response()->noContent();
    }
}
