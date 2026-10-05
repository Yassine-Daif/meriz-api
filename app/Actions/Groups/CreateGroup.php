<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée un groupe. Son créateur en est l'administrateur, et son premier membre.
 */
class CreateGroup
{
    public const QUOTA_MESSAGE = 'Nombre maximal de groupes créés atteint.';

    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $creator, string $name): Group
    {
        return DB::transaction(function () use ($creator, $name) {
            // Verrou sur le compte : deux créations simultanées ne dépassent pas le quota.
            User::whereKey($creator->id)->lockForUpdate()->first();

            if ($creator->createdGroups()->count() >= config('groups.max_per_user')) {
                throw ValidationException::withMessages(['name' => self::QUOTA_MESSAGE]);
            }

            $group = new Group(['name' => $name]);
            // Créateur pris dans le jeton, code généré par le serveur.
            $group->forceFill([
                'creator_id' => $creator->id,
                'join_code' => $this->codes->generate(Group::class),
            ])->save();

            // Le créateur est aussi membre : il apparaît dans la présence.
            $group->members()->attach($creator->id);

            return $group;
        });
    }
}
