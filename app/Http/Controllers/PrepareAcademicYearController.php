<?php

namespace App\Http\Controllers;

use App\Actions\Classrooms\CreateClassroom;
use App\Http\Requests\Collections\PrepareAcademicYearRequest;
use App\Http\Resources\WorkspaceCollectionResource;
use App\Models\TeachingResource;
use App\Models\User;
use App\Models\WorkspaceCollection;
use App\Services\TeachingLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrepareAcademicYearController extends Controller
{
    public function store(PrepareAcademicYearRequest $request, WorkspaceCollection $workspaceCollection, CreateClassroom $create, TeachingLibrary $library)
    {
        $newClassIds = [];
        try {
            $collection = DB::transaction(function () use ($request, $workspaceCollection, $create, $library, &$newClassIds) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $resourceIds = $request->validated('resource_ids');
                sort($resourceIds);
                $fingerprint = hash('sha256', json_encode([$workspaceCollection->id, $request->validated('name'), (int) $request->validated('year_start'), $resourceIds], JSON_THROW_ON_ERROR));
                $existing = WorkspaceCollection::visibleTo($request->user())->where('preparation_key', $request->validated('request_key'))->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->preparation_fingerprint, $fingerprint)) {
                        throw ValidationException::withMessages(['request_key' => 'Cette demande correspond à une autre sélection. Rouvrez la diffusion pour en créer une nouvelle.']);
                    }

                    return $existing;
                }
                if (WorkspaceCollection::visibleTo($request->user())->count() >= 200) {
                    throw ValidationException::withMessages(['name' => 'Nombre maximal de regroupements atteint.']);
                }
                $collection = new WorkspaceCollection($request->safe()->only(['name', 'year_start']));
                $collection->forceFill(['user_id' => $request->user()->id, 'kind' => 'classrooms', 'period_kind' => $workspaceCollection->period_kind, 'archived' => false, 'preparation_key' => $request->validated('request_key'), 'preparation_fingerprint' => $fingerprint])->save();
                foreach ($workspaceCollection->classrooms()->where('teacher_id', $request->user()->id)->orderBy('id')->get() as $oldClass) {
                    $newClassIds[] = $create->handle($request->user(), $oldClass->name)->id;
                }
                $collection->classrooms()->sync($newClassIds);
                if ($newClassIds !== []) {
                    $resources = TeachingResource::visibleTo($request->user())->whereKey($request->validated('resource_ids'))->orderBy('id')->get();
                    foreach ($resources as $resource) {
                        $library->distribute($resource, $newClassIds, false, false, false, null);
                    }
                }

                return $collection;
            });
        } catch (Throwable $exception) {
            // Identifiants créés ici seulement, jamais une ancienne classe.
            foreach ($newClassIds as $id) {
                Storage::disk(config('assignments.image_disk'))->deleteDirectory('assignments/'.$id);
                Storage::disk(config('lessons.disk'))->deleteDirectory('lessons/'.$id);
            }
            throw $exception;
        }

        return (new WorkspaceCollectionResource($collection))->response()->setStatusCode(201);
    }
}
