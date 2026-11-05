<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Une ancienne adhésion à un groupe ne doit pas rester visible.
        $ids = $this->kind === 'classrooms'
            ? $this->classrooms()->where('teacher_id', $request->user()->id)->pluck('classrooms.id')
            : $this->groups()->visibleTo($request->user())->pluck('work_groups.id');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'year_start' => $this->year_start,
            'period_kind' => $this->period_kind ?? 'academic',
            'archived' => $this->archived,
            'item_ids' => $ids,
        ];
    }
}
