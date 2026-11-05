<?php

namespace App\Http\Controllers;

use App\Http\Requests\Library\CaptureResourceRequest;
use App\Http\Requests\Library\DistributeResourceRequest;
use App\Http\Resources\TeachingResourceResource;
use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\TeachingResource;
use App\Services\TeachingLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeachingResourceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isTeacher(), 403);

        return TeachingResourceResource::collection(TeachingResource::visibleTo($request->user())->orderByDesc('updated_at')->get());
    }

    public function store(CaptureResourceRequest $request, TeachingLibrary $library)
    {
        return (new TeachingResourceResource($library->capture($request->user(), $this->source($request))))
            ->response()->setStatusCode(201);
    }

    public function refresh(CaptureResourceRequest $request, TeachingResource $teachingResource, TeachingLibrary $library)
    {
        return new TeachingResourceResource($library->capture($request->user(), $this->source($request), $teachingResource));
    }

    public function distribute(DistributeResourceRequest $request, TeachingResource $teachingResource, TeachingLibrary $library)
    {
        return response()->json(['data' => $library->distribute(
            $teachingResource, $request->validated('classroom_ids'), $request->boolean('update_existing'),
            $request->boolean('publish'), $request->boolean('apply_settings'),
            $request->filled('due_at') ? Carbon::parse($request->validated('due_at'))->utc()->toIso8601String() : null,
        )]);
    }

    public function destroy(Request $request, TeachingResource $teachingResource)
    {
        abort_unless($request->user()->isTeacher() && $teachingResource->user_id === $request->user()->id, 403);
        $teachingResource->delete();

        return response()->noContent();
    }

    private function source(CaptureResourceRequest $request): Assignment|Lesson
    {
        $class = $request->validated('source_kind') === 'assignment' ? Assignment::class : Lesson::class;

        return $class::whereHas('classroom', fn ($query) => $query->where('teacher_id', $request->user()->id))
            ->whereKey($request->validated('source_id'))->firstOrFail();
    }
}
