<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Désigne, ou retire, un administrateur.
 *
 * C'est la seule porte : rien dans l'application, et rien dans le panneau
 * non plus. Un admin ne doit pas pouvoir en fabriquer un autre d'un clic ;
 * il faut un accès au serveur.
 */
class ManageAdmin extends Command
{
    protected $signature = 'meriz:admin {email : Adresse email du compte} {--revoke : Retirer le droit au lieu de le donner}';

    protected $description = "Donne ou retire l'accès à la zone d'administration";

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::firstWhere('email', $email);

        if ($user === null) {
            $this->components->error("Aucun compte avec l'adresse {$email}.");

            return self::FAILURE;
        }

        if ($user->isAnonymized()) {
            $this->components->error('Ce compte a été supprimé par son titulaire, il ne peut pas être administrateur.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');

        $user->forceFill(['is_admin' => $grant])->save();

        $this->components->info($grant
            ? "{$user->first_name} {$user->name} ({$email}) administre désormais la plateforme."
            : "{$user->first_name} {$user->name} ({$email}) n'est plus administrateur.");

        if ($grant) {
            $this->components->twoColumnDetail('Zone admin', url('/admin'));
        }

        return self::SUCCESS;
    }
}
