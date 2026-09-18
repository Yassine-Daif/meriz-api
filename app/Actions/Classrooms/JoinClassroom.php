<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fait rejoindre une classe à l'utilisateur authentifié, par son code.
 *
 * Un code inconnu donne toujours le même message, sans rien révéler.
 * Rejoindre une classe dont on est déjà membre ne change rien.
 */
class JoinClassroom
{
    public const INVALID_CODE_MESSAGE = 'Code de classe invalide.';

    public const OWNER_MESSAGE = 'Vous êtes le prof de cette classe.';

    public const FULL_MESSAGE = 'Cette classe est complète.';

    public const TOO_MANY_MESSAGE = 'Nombre maximal de classes rejointes atteint.';

    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $code): Classroom
    {
        $code = $this->codes->normalize($code);

        return DB::transaction(function () use ($user, $code) {
            $classroom = $code === ''
                ? null
                : Classroom::where('join_code', $code)->lockForUpdate()->first();

            if (! $classroom) {
                $this->fail(self::INVALID_CODE_MESSAGE);
            }

            if ($classroom->isTaughtBy($user)) {
                $this->fail(self::OWNER_MESSAGE);
            }

            if ($classroom->hasMember($user)) {
                return $classroom;
            }

            if ($classroom->members()->count() >= config('classrooms.max_members')) {
                $this->fail(self::FULL_MESSAGE);
            }

            if ($user->classrooms()->count() >= config('classrooms.max_memberships_per_user')) {
                $this->fail(self::TOO_MANY_MESSAGE);
            }

            // L'élève vient du jeton, jamais du client.
            $classroom->members()->attach($user->id);

            return $classroom;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['code' => $message]);
    }
}
