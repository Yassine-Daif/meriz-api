<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Classement personnel : ne change jamais les droits des classes ou groupes. */
#[Fillable(['name', 'year_start', 'archived'])]
class WorkspaceCollection extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['year_start' => 'integer', 'archived' => 'boolean'];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'workspace_collection_classroom', 'collection_id', 'classroom_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'workspace_collection_group', 'collection_id', 'group_id');
    }
}
