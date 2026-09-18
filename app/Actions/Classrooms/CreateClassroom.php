<?php

namespace App\Actions\Classrooms;

use App\Models\Classroom;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crée une classe pour le prof authentifié, dans la limite de son quota.
 */
class CreateClassroom
{
    public const QUOTA_MESSAGE = 'Nombre maximal de classes atteint.';

    public function __construct(private readonly JoinCodeGenerator $codes) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $teacher, string $name): Classroom
    {
        return DB::transaction(function () use ($teacher, $name) {
            // Verrou sur le compte : deux créations simultanées ne dépassent pas le quota.
            User::whereKey($teacher->id)->lockForUpdate()->first();

            if ($teacher->taughtClassrooms()->count() >= config('classrooms.max_per_teacher')) {
                throw ValidationException::withMessages(['name' => self::QUOTA_MESSAGE]);
            }

            $classroom = new Classroom(['name' => $name]);
            // Le prof vient du jeton, le code du serveur : jamais du client.
            $classroom->forceFill([
                'teacher_id' => $teacher->id,
                'join_code' => $this->codes->generate(),
            ])->save();

            return $classroom;
        });
    }
}
