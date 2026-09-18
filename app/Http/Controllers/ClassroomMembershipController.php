<?php

namespace App\Http\Controllers;

use App\Actions\Classrooms\JoinClassroom;
use App\Http\Requests\Classrooms\JoinClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ClassroomMembershipController extends Controller
{
    public function join(JoinClassroomRequest $request, JoinClassroom $joinClassroom): ClassroomResource
    {
        $classroom = $joinClassroom->handle($request->user(), $request->validated('code'));

        $classroom->load([
            'teacher',
            'members' => fn ($query) => $query->orderBy('name')->orderBy('first_name'),
        ])->loadCount('members');

        return new ClassroomResource($classroom);
    }

    public function leave(Request $request, Classroom $classroom): Response
    {
        Gate::authorize('leave', $classroom);

        $classroom->members()->detach($request->user()->id);

        return response()->noContent();
    }

    /**
     * {member} n'est cherché que parmi les membres de cette classe.
     */
    public function remove(Classroom $classroom, User $member): Response
    {
        Gate::authorize('manage', $classroom);

        $classroom->members()->detach($member->id);

        return response()->noContent();
    }
}
