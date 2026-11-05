<?php

namespace App\Http\Controllers;

use App\Models\TeachingResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeachingResourceMediumController extends Controller
{
    public function show(Request $request, TeachingResource $teachingResource, string $asset)
    {
        abort_unless($request->user()->isTeacher() && $teachingResource->user_id === $request->user()->id, 404);
        $payload = $teachingResource->payload;
        $medium = $asset === 'image' ? ($payload['image'] ?? null)
            : (ctype_digit($asset) && (int) $asset < 50 ? ($payload['media'][(int) $asset] ?? null) : null);
        abort_unless($medium && Storage::disk('local')->exists($medium['path']), 404);

        return Storage::disk('local')->download($medium['path'], $medium['name'] ?? 'image', ['Content-Type' => $medium['mime']]);
    }
}
