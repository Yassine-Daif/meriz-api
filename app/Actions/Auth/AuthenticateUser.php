<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Vérifie des identifiants et émet un jeton.
 *
 * Le message d'échec est le même que l'email soit inconnu ou le mot de
 * passe faux. Un hash factice est vérifié quand l'email est inconnu, pour
 * que le temps de réponse ne trahisse pas l'existence du compte.
 */
class AuthenticateUser
{
    public const FAILED_MESSAGE = 'Email ou mot de passe incorrect.';

    public function __construct(private readonly IssueToken $issueToken) {}

    /**
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password, ?string $deviceName = null): array
    {
        $user = User::where('email', Str::lower(trim($email)))->first();

        $valid = Hash::check($password, $user?->password ?? $this->dummyHash());

        // Un compte supprimé par son titulaire est éteint : son email a
        // changé, donc on n'arrive déjà pas ici, et c'est la seconde
        // barrière. Même message, aucune information de plus.
        if (! $user || ! $valid || $user->isAnonymized()) {
            throw ValidationException::withMessages([
                'email' => self::FAILED_MESSAGE,
            ]);
        }

        return [
            'user' => $user,
            'token' => $this->issueToken->handle($user, $deviceName),
        ];
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(Str::random(40));
    }
}
