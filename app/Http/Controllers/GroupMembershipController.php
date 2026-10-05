<?php

namespace App\Http\Controllers;

use App\Actions\Groups\JoinGroup;
use App\Http\Requests\Groups\JoinGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GroupMembershipController extends Controller
{
    public function join(JoinGroupRequest $request, JoinGroup $joinGroup): GroupResource
    {
        $group = $joinGroup->handle($request->user(), $request->validated('code'));

        $group->load([
            'creator',
            'members' => fn ($query) => $query->orderBy('name')->orderBy('first_name'),
        ])->loadCount(['members', 'documents']);

        return new GroupResource($group);
    }

    public function leave(Request $request, Group $group): Response
    {
        Gate::authorize('leave', $group);

        $group->members()->detach($request->user()->id);

        return response()->noContent();
    }

    /**
     * {participant} n'est cherché que parmi les membres de ce groupe.
     */
    public function remove(Group $group, User $participant): Response
    {
        Gate::authorize('manage', $group);

        $group->members()->detach($participant->id);

        return response()->noContent();
    }
}
