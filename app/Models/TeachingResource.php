<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Copie privée et autonome, même si la classe d'origine est supprimée. */
class TeachingResource extends Model
{
    use HasUlids;

    protected $guarded = ['*'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer', 'storage_bytes' => 'integer'];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    protected static function booted(): void
    {
        static::deleted(fn (TeachingResource $resource) => Storage::disk('local')->deleteDirectory('teaching-library/'.$resource->id));
    }
}
