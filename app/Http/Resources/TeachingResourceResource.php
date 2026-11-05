<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class TeachingResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'kind' => $this->kind, 'title' => $this->title, 'version' => $this->version,
            'source_id' => $this->source_id,
            'storage_bytes' => $this->storage_bytes, 'updated_at' => $this->updated_at?->toIso8601String(),
            'publications' => DB::table('teaching_publications')->where('resource_id', $this->id)
                ->get(['classroom_id', 'assignment_id', 'lesson_id', 'version']),
        ];
    }
}
