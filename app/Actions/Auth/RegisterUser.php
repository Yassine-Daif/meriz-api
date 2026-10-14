<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AcademicEmailChecker;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Crée un compte. L'inscription est neutre : tout compte démarre élève,
 * et son statut scolaire est calculé à partir du domaine de l'email.
 */
class RegisterUser
{
    public function __construct(
        private readonly AcademicEmailChecker $academic,
        private readonly IssueToken $issueToken,
    ) {}

    /**
     * @param  array{name: string, first_name: string, email: string, password: string, device_name?: string|null}  $data
     * @return array{user: User, token: string}
     */
    public function handle(array $data): array
    {
        $email = Str::lower(trim($data['email']));

        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'first_name' => $data['first_name'],
            'email' => $email,
            'password' => $data['password'],
            'role' => UserRole::Student,
            'is_academic' => $this->academic->isAcademic($email),
        ])->save();

        $this->sendVerificationLink($user);

        return [
            'user' => $user,
            'token' => $this->issueToken->handle($user, $data['device_name'] ?? null),
        ];
    }

    /**
     * L'envoi est direct, donc il peut échouer. Un service mail en panne ne
     * doit pas faire échouer une inscription : le compte existe, la
     * personne redemandera le lien.
     */
    private function sendVerificationLink(User $user): void
    {
        try {
            event(new Registered($user));
        } catch (Throwable $e) {
            Log::error("Envoi du lien de vérification impossible pour l'utilisateur {$user->id}.", [
                'exception' => $e,
            ]);
        }
    }
}
