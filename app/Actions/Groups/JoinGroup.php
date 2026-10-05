<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fait rejoindre un groupe par son code.
 *
 * Un code inconnu donne toujours le même message, sans rien révéler.
 * Rejoindre un groupe dont on est déjà membre ne change rien.
 */
class JoinGroup
{
    public const INVALID_CODE_MESSAGE = 'Code de groupe invalide.';

    public const FULL_MESSAGE = 'Ce groupe est complet.';

    public const TOO_MANY_MESSAGE = 'Nombre maximal de groupes rejoints atteint.';

    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $code): Group
    {
        $code = $this->codes->normalize($code);

        return DB::transaction(function () use ($user, $code) {
            $group = $code === ''
                ? null
                : Group::where('join_code', $code)->lockForUpdate()->first();

            if (! $group) {
                $this->fail(self::INVALID_CODE_MESSAGE);
            }

            if ($group->hasMember($user)) {
                return $group;
            }

            if ($group->members()->count() >= config('groups.max_members')) {
                $this->fail(self::FULL_MESSAGE);
            }

            if ($user->groups()->count() >= config('groups.max_memberships_per_user')) {
                $this->fail(self::TOO_MANY_MESSAGE);
            }

            // Le membre vient du jeton, jamais du client.
            $group->members()->attach($user->id);

            return $group;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['code' => $message]);
    }
}
