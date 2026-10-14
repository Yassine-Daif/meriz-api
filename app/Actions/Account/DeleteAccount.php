<?php

namespace App\Actions\Account;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Supprime un compte, définitivement.
 *
 * La ligne users n'est pas détruite, elle est vidée : un rendu déjà chez un
 * prof et des commentaires dans des fils y renvoient encore, et toutes les
 * clés étrangères vers users sont en cascade. Détruire la ligne les
 * emporterait, alors que la règle demande de les garder, détachés de
 * l'identité, sous « Compte supprimé ».
 *
 * Tout le reste part pour de vrai : documents personnels, inscriptions,
 * classes et groupes dont la personne est responsable, et les fichiers qui
 * vont avec, effacés par les crochets déjà posés sur ces modèles.
 */
class DeleteAccount
{
    public function handle(User $user): User
    {
        DB::transaction(function () use ($user) {
            // 1. Elle sort des classes et des groupes des autres.
            $user->classrooms()->detach();
            $user->groups()->detach();

            // 2. Les groupes qu'elle a créés disparaissent, avec leurs
            //    membres et leurs documents partagés (cascade en base).
            $user->createdGroups()->each(fn (Group $group) => $group->delete());

            // 3. Ses classes aussi : le crochet de Classroom efface les
            //    dossiers de médias, la base emporte devoirs, cours,
            //    médias et rendus. Le travail des élèves survit, car
            //    documents.assignment_id est en nullOnDelete.
            $user->taughtClassrooms()->each(fn (Classroom $classroom) => $classroom->delete());

            // 4. Ses documents personnels, et leurs commentaires en
            //    cascade. Un document de groupe reste à l'espace commun.
            $user->documents()->personal()->each(fn (Document $document) => $document->delete());

            // 5. Plus aucune session.
            $user->tokens()->delete();

            // 6. La ligne est vidée de tout ce qui identifie.
            $user->forceFill([
                'name' => User::DELETED_NAME,
                'first_name' => null,
                'email' => 'supprime-'.$user->id.'@compte.invalid',
                'email_verified_at' => null,
                'password' => Str::random(64),
                'role' => UserRole::Student,
                'is_academic' => false,
                'bio' => null,
                'bio_shared' => false,
                'contact' => null,
                'contact_shared' => false,
                'avatar_bg' => null,
                'avatar_fg' => null,
                'remember_token' => null,
                'anonymized_at' => Carbon::now(),
            ])->save();
        });

        return $user;
    }
}
